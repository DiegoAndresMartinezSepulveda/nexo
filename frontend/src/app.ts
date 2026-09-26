import { Component, inject, signal, computed, OnInit, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import {Block, Media, NoteEditorComponent, ModalComponent} from './editor';
import {Diagram,DiagramComponent,emptyDiagram} from './diagram';
import {ReadingComponent} from './reading';
import {IconComponent} from './icons';

type Catalog={id:number;name:string;code?:string;client_id?:number|null;description?:string};
type Activity={id:number;action:string;subject_type:string;subject_id:number;title:string;created_at:string};
type Share={user_id:number;name?:string;email?:string;permission:'view'|'edit'};
type Member={id:number;name:string;email:string};
type Entry={diagram?:Diagram;id:number;kind:'note'|'library'|'diagram';title:string;blocks:Block[];search_text:string;client_id:number|null;project_id:number|null;category:string;tags:string[];color:string;pinned:boolean;archived:boolean;media:Media[];updated_at:string;visibility?:'private'|'workspace'|'shared';shares?:Share[];can_edit?:boolean};
type User = {id: number; name: string; email: string;role:'admin'|'editor'|'reader';workspace_ids?:number[]};
type Space={id:number;name:string;color:string};
type NotificationContact={id:number;name:string;email:string;channel:'email'|'message';is_default:boolean};
type Attachment = {id: number; name: string; size: number};
type Step = {text: string; done: boolean};
type Task = {task_type:'task'|'bug';tags?:string[];is_fire:boolean;notify_on_production:boolean;notify_emails?:string[];notify_message_on_production:boolean;notify_message_emails?:string[];notification_message?:string;notification_message_short?:string;notification_fields?:string[];notification?:string;production_return_reason?:string|null;production_returned_at?:string|null;client_id?:number|null; project_id?:number|null; notes_blocks?:Block[];description_blocks?:Block[]; sql_notes?:string|null; id: number; title: string; description: string | null; status: string; environment: string; priority: string; due_date: string | null; estimated_delivery_at?: string | null; checklist: Step[]; attachments?: Attachment[]; attachments_count?: number; updated_at: string; created_at: string};
type Draft = {task_type:'task'|'bug';tags:string;is_fire:boolean;notify_on_production:boolean;notify_emails:string;notification_message:string;notify_message_on_production:boolean;notify_message_emails:string;notification_message_short:string;notify_include_client:boolean;notify_include_project:boolean;notify_include_title:boolean;notify_include_description:boolean;notify_include_code:boolean;notify_include_status:boolean;notify_include_checklist:boolean;notify_include_attachments:boolean;estimated_delivery_at:string;client_id:string; project_id:string; sql_notes:string; title: string; description: string; status: string; environment: string; priority: string; checklist_text: string};

@Component({selector: 'app-root', standalone: true, imports: [CommonModule, FormsModule, NoteEditorComponent, ModalComponent, IconComponent, DiagramComponent, ReadingComponent], templateUrl: './workspace.html'})
export class AppComponent implements OnInit {
  private http = inject(HttpClient);
  constructor(){effect((cleanup)=>{if(this.notice()){const timer=setTimeout(()=>this.notice.set(''),5500);cleanup(()=>clearTimeout(timer));}});}

  user = signal<User | null>(null);
  ready = signal(false);
  busy = signal(false);
  loading = signal(false);
  error = signal('');
  notice = signal('');
  autosaveState=signal<'idle'|'saving'|'saved'>('idle');private autosaveSnapshot='';private autosaveTimer:any;
  view = signal<string>('dashboard');
  tasks = signal<Task[]>([]);
  counts = signal<Record<string, number>>({});
  selected = signal<Task | null>(null);
  files: File[] = [];
  email = ''; password = ''; query = ''; environment = '';taskTag='';
  draft: Draft = this.emptyDraft();
  statusLabels: Record<string, string> = {pending: 'Pendiente', development: 'En desarrollo', review: 'En revisión', done: 'Completada'};
  environmentLabels: Record<string, string> = {backlog: 'Backlog', local: 'Local', development: 'Desarrollo', qa:'QA', certification: 'Certificación', production: 'Producción'};
  priorityLabels: Record<string, string> = {low: 'Baja', normal: 'Normal', high: 'Alta', urgent: 'Urgente'};
  statuses = Object.entries(this.statusLabels);
  get environments(){return Object.entries(this.environmentLabels).filter(e=>e[0]!=='qa'||this.tasks().some(t=>t.environment==='qa')||this.draft.environment==='qa');}
  priorities = Object.entries(this.priorityLabels);
  total = computed(() => Object.values(this.counts()).reduce((sum, n) => sum + n, 0));
  private emptyDraft(): Draft { return {task_type:'task',tags:'',is_fire:false,notify_on_production:false,notify_emails:'',notification_message:'¡Listo! 🚀 La tarea ya está en Producción.',notify_message_on_production:false,notify_message_emails:'',notification_message_short:'',notify_include_client:true,notify_include_project:true,notify_include_title:true,notify_include_description:true,notify_include_code:true,notify_include_status:true,notify_include_checklist:true,notify_include_attachments:false,estimated_delivery_at:'',client_id:'', project_id:'', sql_notes:'', title: '', description: '', status: 'pending', environment: 'local', priority: 'normal', checklist_text: ''}; }


  clients=signal<Catalog[]>([]); projects=signal<Catalog[]>([]);
  notes=signal<Entry[]>([]); library=signal<Entry[]>([]); history=signal<Activity[]>([]); taskHistory=signal<Activity[]>([]);
  historyPage=1; historyLast=1;
  taskBlocks:Block[]=[];taskDescriptionBlocks:Block[]=[]; entryBlocks:Block[]=[];
  uploadBusy=signal(false); attachmentBusy=signal(false);
  theme=signal(localStorage.getItem('flujo-theme')||'light'); collapsed=signal(localStorage.getItem('flujo-sidebar')==='collapsed'); mobileOpen=signal(false);
  groupBy=signal(localStorage.getItem('flujo-group')||'environment'); autoEnvironment=signal(localStorage.getItem('flujo-auto-environment')==='yes');
  clientFilter='';projectFilter='';entryQuery='';entryClient='';entryProject='';entryCategory='';entryTag='';entryType='';archived=false;
  activeKind:'note'|'library'|'diagram'='note'; selectedEntry=signal<Entry|null>(null); entryMedia=signal<Media[]>([]);
  entryDraft={title:'',color:'yellow',pinned:false,archived:false,client_id:'',project_id:'',category:'',tags:'',visibility:'private' as 'private'|'workspace'|'shared',shares:[] as Share[]};
  colors=[['yellow','Amarillo'],['blue','Azul'],['green','Verde'],['red','Rojo'],['purple','Morado'],['gray','Gris']];
  catalogKind:'clients'|'projects'='clients';catalogId:number|null=null;catalogDraft={name:'',code:'',description:'',client_id:''};catalogModal=signal(false);
  moveTask=signal<Task|null>(null);moveEnvironment='local';moveStatus='development';moveReason='';dragOver=signal('');
  previewMedia=signal<Media|null>(null);
  nav=[['dashboard','grid','Inicio'],['board','columns','Tablero'],['bugs','spark','Bugs'],['notes','note','Notas importantes'],['library','folder','Biblioteca'],['diagrams','layers','Diagramas']];
  management=[['clients','users','Clientes'],['projects','layers','Proyectos'],['history','history','Historial']];
  pageNames:Record<string,string>={dashboard:'Inicio',board:'Tablero',bugs:'Bugs',notes:'Notas importantes',library:'Biblioteca',clients:'Clientes',projects:'Proyectos',history:'Historial',diagrams:'Diagramas',users:'Usuarios',spaces:'Espacios',reading:'Lectura',taskreading:'Lectura de tarea',settings:'Preferencias',detail:'Requerimiento',entry:'Editor'};
  get currentTitle(){return this.pageNames[this.view()]||'Mi espacio';}
  allowedStatuses(env:string){return this.statuses.filter(s=>env==='backlog'?s[0]==='pending':['certification','qa','production'].includes(env)?['review','done'].includes(s[0]):['development','review','done'].includes(s[0]));}
  defaultStatus(env:string,wanted='development'){return this.allowedStatuses(env).some(s=>s[0]===wanted)?wanted:'review';}
  taskEnvironmentChanged(){this.draft.status=this.defaultStatus(this.draft.environment,this.draft.status);}
  get columns(){return this.groupBy()==='environment'?this.environments:this.statuses;}
  get today(){return new Date().toLocaleDateString('es-CL',{weekday:'long',day:'numeric',month:'long'});}
  get pending(){return this.total()-(this.counts()['done']||0);}
  clientName(id?:number|null){const c=this.clients().find(c=>c.id===id);return c?c.name+(c.code?' · '+c.code:''):'General';}
  projectName(id?:number|null){return this.projects().find(c=>c.id===id)?.name||'';}
  formattedDescription(task:Task):Block[]{return task.description_blocks?.length?task.description_blocks:[{type:'text',text:task.description||''}];}
  projectOptions(client:string){return this.projects().filter(p=>String(p.client_id||'')===client);}
  visibleTasks(){const q=this.query.toLocaleLowerCase();return this.tasks().filter(t=>(!this.environment||t.environment===this.environment)&&(!this.clientFilter||String(t.client_id)===this.clientFilter)&&(!this.projectFilter||String(t.project_id)===this.projectFilter)&&(!this.taskTag||(t.tags||[]).includes(this.taskTag))&&(!q||(t.title+' '+(t.description||'')+' '+(t.tags||[]).join(' ')+' '+this.clientName(t.client_id)).toLocaleLowerCase().includes(q)));}
  taskTags(){return [...new Set(this.tasks().flatMap(t=>t.tags||[]))].sort();}
  visibleEntries(){const q=this.entryQuery.toLocaleLowerCase();const list=this.view()==='library'?this.library():this.view()==='diagrams'?this.diagrams():this.notes();return list.filter(e=>(!q||(e.title+' '+e.search_text+' '+e.tags.join(' ')).toLocaleLowerCase().includes(q))&&(!this.entryClient||String(e.client_id)===this.entryClient)&&(!this.entryProject||String(e.project_id)===this.entryProject)&&(!this.entryCategory||e.category===this.entryCategory)&&(!this.entryTag||e.tags.includes(this.entryTag))&&(!this.entryType||e.media.some(m=>this.fileType(m)===this.entryType)));}
  fileType(m:Media){return m.mime.startsWith('image/')?'image':m.mime==='application/pdf'?'pdf':m.name.toLowerCase().endsWith('.sql')?'sql':m.name.toLowerCase().endsWith('.zip')?'archive':'document';}
  isImageName(name:string){return /\.(png|jpe?g|webp|gif)$/i.test(name);}
  firstImage(e:Entry){return e.blocks.find(b=>b.type==='image')?.media_id;}
  excerpt(e:Entry){return (e.search_text||'').slice(0,180);}
  entryCategories(){return [...new Set(this.library().map(e=>e.category).filter(Boolean))];}
  entryTags(){return [...new Set(this.library().flatMap(e=>e.tags))];}
  initializeTheme(){document.documentElement.dataset['theme']=this.theme();}
  toggleTheme(){this.theme.set(this.theme()==='light'?'dark':'light');localStorage.setItem('flujo-theme',this.theme());this.initializeTheme();}
  toggleSidebar(){this.collapsed.update(v=>!v);localStorage.setItem('flujo-sidebar',this.collapsed()?'collapsed':'expanded');}
  setGroup(value:string){this.groupBy.set(value);localStorage.setItem('flujo-group',value);}
  setAuto(value:boolean){this.autoEnvironment.set(value);localStorage.setItem('flujo-auto-environment',value?'yes':'no');}
  async loadWorkspace(){this.pageLoading.set(true);try{await Promise.all([this.loadTasks(),this.loadCatalog(),this.loadEntries('note'),this.loadEntries('library'),this.loadEntries('diagram'),this.loadHistory(),this.loadNotificationContacts()]);}finally{this.pageLoading.set(false);}}
  async loadNotificationContacts(){try{this.notificationContacts.set(await firstValueFrom(this.http.get<NotificationContact[]>('/api/notification-contacts')));}catch(e){this.showError(e);}}
  defaultRecipients(channel:'email'|'message'){return this.notificationContacts().filter(c=>c.channel===channel&&c.is_default).map(c=>c.email).join(', ');}
  async loadCatalog(){const generation=this.generation;try{const c=await firstValueFrom(this.http.get<{clients:Catalog[];projects:Catalog[]}>('/api/catalog'));if(generation!==this.generation)return;this.clients.set(c.clients);this.projects.set(c.projects);}catch(e){this.showError(e);}}
  async loadEntries(kind:'note'|'library'|'diagram'){const generation=this.generation;try{const entries=await firstValueFrom(this.http.get<Entry[]>('/api/entries',{params:{kind,archived:this.archived?'1':'0'}}));if(generation!==this.generation)return;(kind==='note'?this.notes:kind==='diagram'?this.diagrams:this.library).set(entries);}catch(e){this.showError(e);}}
  async loadHistory(taskId?:number,page=1){const generation=this.generation;try{const data=await firstValueFrom(this.http.get<{data:Activity[];last_page:number}>('/api/history',{params:taskId?{task_id:taskId}:{page}}));if(generation!==this.generation)return;if(taskId)this.taskHistory.set(data.data);else{this.history.set(data.data);this.historyPage=page;this.historyLast=data.last_page;}}catch(e){this.showError(e);}}
  async navigate(page:string){
    if(this.busy()||this.pageLoading()||this.uploadBusy()||this.attachmentBusy())return;
    this.mobileOpen.set(false);this.error.set('');this.notice.set('');this.view.set(page);this.pageLoading.set(true);window.scrollTo(0,0);
    try{if(page==='dashboard')await this.loadWorkspace();if(page==='board'||page==='bugs')await this.loadTasks();if(['notes','library','diagrams'].includes(page)){this.archived=false;await this.loadEntries(this.kindForView());}if(page==='history')await this.loadHistory();if(page==='users')await this.loadUsers();if(page==='settings')await this.loadNotificationContacts();}finally{this.pageLoading.set(false);}
  }
  kindForView():'note'|'library'|'diagram'{return this.view()==='notes'?'note':this.view()==='diagrams'?'diagram':'library';}
  entryList(){return this.activeKind==='note'?'notes':this.activeKind==='diagram'?'diagrams':'library';}
  async toggleArchiveView(){if(this.pageLoading())return;this.archived=!this.archived;this.pageLoading.set(true);try{await this.loadEntries(this.kindForView());}finally{this.pageLoading.set(false);}}
  async filterEntries(){await this.loadEntries(this.kindForView());}
  async newEntry(kind:'note'|'library'|'diagram'){if(!this.canEdit()||this.busy()||this.pageLoading())return;this.entryDiagram=emptyDiagram();this.activeKind=kind;this.selectedEntry.set(null);this.entryDraft={title:'',color:kind==='note'?'yellow':'blue',pinned:false,archived:false,client_id:'',project_id:'',category:'',tags:'',visibility:'private',shares:[]};this.entryBlocks=[{type:'text',text:''}];this.entryMedia.set([]);if(kind==='diagram')await this.loadMembers();this.view.set('entry');this.notice.set('');this.error.set('');this.mobileOpen.set(false);}
  async openEntry(e:Entry){this.entryDiagram=structuredClone(e.diagram||emptyDiagram());this.activeKind=e.kind;this.selectedEntry.set(e);this.entryDraft={title:e.title,color:e.color,pinned:e.pinned,archived:e.archived,client_id:e.client_id?String(e.client_id):'',project_id:e.project_id?String(e.project_id):'',category:e.category||'',tags:e.tags.join(', '),visibility:e.visibility||'workspace',shares:[...(e.shares||[])]};this.entryBlocks=structuredClone(e.blocks.length?e.blocks:[{type:'text',text:''}]);this.entryMedia.set([...e.media]);if(e.kind==='diagram'&&e.can_edit)await this.loadMembers();this.view.set(this.defaultEdit()&&this.canEditEntry(e)?'entry':'reading');this.error.set('');this.notice.set('');window.scrollTo(0,0);}
  async saveEntry(auto=false){if(this.busy()||this.uploadBusy()||this.attachmentBusy())return;this.busy.set(true);if(auto)this.autosaveState.set('saving');this.error.set('');try{
    const oldImages=new Set(this.selectedEntry()?.blocks.filter(b=>b.type==='image').map(b=>b.media_id)||[]);
    const currentImages=new Set(this.entryBlocks.filter(b=>b.type==='image').map(b=>b.media_id));
    const attachment_ids=this.entryMedia().filter(m=>!oldImages.has(m.id)||currentImages.has(m.id)).map(m=>m.id);
    const payload={...this.entryDraft,kind:this.activeKind,client_id:this.entryDraft.client_id||null,project_id:this.entryDraft.project_id||null,tags:this.entryDraft.tags.split(',').map(t=>t.trim()).filter(Boolean),blocks:this.entryBlocks,...(this.activeKind==='diagram'?{diagram:this.entryDiagram}:{}),attachment_ids};
    const id=this.selectedEntry()?.id;
    const result=await firstValueFrom(id?this.http.put<Entry>('/api/entries/'+id,payload):this.http.post<Entry>('/api/entries',payload));
    this.selectedEntry.set(result);this.entryMedia.set(result.media);this.autosaveSnapshot=this.currentAutosaveSnapshot();if(auto)this.autosaveState.set('saved');else{this.notice.set('Guardado. Tus notas y archivos están al día.');await this.loadEntries(this.activeKind);}
  }catch(e){this.showError(e);}finally{this.busy.set(false);}}
  async updateEntry(e:Entry,change:Partial<Entry>){if(this.busy())return;this.busy.set(true);this.error.set('');try{await firstValueFrom(this.http.put('/api/entries/'+e.id,{...e,...change,attachment_ids:e.media.map(m=>m.id)}));await this.loadEntries(e.kind);}catch(err){this.showError(err);}finally{this.busy.set(false);}}
  async deleteEntry(){const e=this.selectedEntry();if(!e||this.busy()||!confirm('¿Eliminar esta entrada y sus archivos? No se puede deshacer.'))return;this.busy.set(true);try{await firstValueFrom(this.http.delete('/api/entries/'+e.id));this.view.set(this.entryList());await this.loadEntries(e.kind);this.notice.set('Entrada eliminada.');}catch(err){this.showError(err);}finally{this.busy.set(false);}}
  async uploadFiles(files:File[]){if(this.attachmentBusy()||!files.length)return;if(files.length>8||files.some(f=>f.size>10485760)){this.error.set('Sube hasta 8 archivos de máximo 10 MB por envío.');return;}this.attachmentBusy.set(true);this.error.set('');try{for(const file of files){const data=new FormData();data.append('file',file);const media=await firstValueFrom(this.http.post<Media>('/api/media',data));this.entryMedia.update(a=>[...a,media]);}}catch(e){this.showError(e);}finally{this.attachmentBusy.set(false);}}
  pickLibraryFiles(event:Event){const input=event.target as HTMLInputElement;this.uploadFiles(Array.from(input.files||[]));input.value='';}
  dropLibraryFiles(event:DragEvent){event.preventDefault();this.uploadFiles(Array.from(event.dataTransfer?.files||[]));}
  removeEntryFile(m:Media){this.entryMedia.update(a=>a.filter(f=>f.id!==m.id));this.entryBlocks=this.entryBlocks.filter(b=>b.media_id!==m.id);}
  editCatalog(kind:'clients'|'projects',item?:Catalog){this.catalogKind=kind;this.catalogId=item?.id||null;this.catalogDraft={code:item?.code||'',name:item?.name||'',description:item?.description||'',client_id:item?.client_id?String(item.client_id):''};this.catalogModal.set(true);}
  async saveCatalog(){if(this.busy())return;this.busy.set(true);this.error.set('');try{const payload={...this.catalogDraft,client_id:this.catalogDraft.client_id||null};await firstValueFrom(this.catalogId?this.http.put('/api/catalog/'+this.catalogKind+'/'+this.catalogId,payload):this.http.post('/api/catalog/'+this.catalogKind,payload));this.catalogModal.set(false);await this.loadCatalog();await this.loadTasks();this.notice.set('Guardado.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  async deleteCatalog(kind:'clients'|'projects',item:Catalog){if(this.busy()||!confirm('¿Eliminar '+item.name+' del catálogo? Las tareas y documentos se conservarán sin esta asociación.'))return;this.busy.set(true);try{await firstValueFrom(this.http.delete('/api/catalog/'+kind+'/'+item.id));await this.loadWorkspace();}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  drag(event:DragEvent,task:Task){event.dataTransfer?.setData('application/x-flujo-task',String(task.id));if(event.dataTransfer)event.dataTransfer.effectAllowed='move';}
  requiresProductionReturnReason(task:Task|null,environment:string){return !!task&&task.environment==='production'&&task.status==='done'&&environment==='development';}
  async dropTask(event:DragEvent,key:string){event.preventDefault();this.dragOver.set('');const id=Number(event.dataTransfer?.getData('application/x-flujo-task'));const task=this.tasks().find(t=>t.id===id);if(!task||this.busy())return;if(this.groupBy()==='status'){await this.move(task,key);return;}if(task.environment===key)return;this.moveEnvironment=key;this.moveStatus=this.defaultStatus(key);this.moveReason='';if(this.autoEnvironment()&&!this.requiresProductionReturnReason(task,key))await this.performMove(task,key,this.defaultStatus(key));else this.moveTask.set(task);}
  showMove(task:Task){this.moveTask.set(task);this.moveEnvironment=task.environment;this.moveStatus=task.status;this.moveReason='';}
  async confirmMove(){const task=this.moveTask();if(task)await this.performMove(task,this.moveEnvironment,this.moveStatus,this.moveReason);}
  async performMove(task:Task,environment:string,status:string,rollbackReason=''){if(this.busy())return;this.busy.set(true);this.error.set('');try{const payload:{environment:string;status:string;rollback_reason?:string}={environment,status};if(this.requiresProductionReturnReason(task,environment))payload.rollback_reason=rollbackReason.trim();const result=await firstValueFrom(this.http.patch<Task>('/api/tasks/'+task.id+'/move',payload));this.moveTask.set(null);await this.loadTasks();this.notice.set(result.notification==='sent'?'Tarea movida y correo enviado.':result.notification==='failed'?'Tarea movida, pero falló el envío del correo.':'Tarea movida a '+this.environmentLabels[environment]+'.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}

  spaces=signal<Space[]>([]);spaceId=signal(0);pageLoading=signal(false);private generation=0;
  diagrams=signal<Entry[]>([]);entryDiagram:Diagram=emptyDiagram();fullscreen=signal(false);members=signal<Member[]>([]);
  defaultEdit=signal(localStorage.getItem('nexo-default-edit')==='yes');
  canEdit=computed(()=>this.user()?.role!=='reader');isAdmin=computed(()=>this.user()?.role==='admin');
  canEditEntry(entry:Entry|null=this.selectedEntry()){return !!this.canEdit()&&(!entry||entry.kind!=='diagram'||entry.can_edit!==false);}
  async loadMembers(){try{this.members.set(await firstValueFrom(this.http.get<Member[]>('/api/members')));}catch(e){this.showError(e);}}
  sharePermission(id:number){return this.entryDraft.shares.find(s=>s.user_id===id)?.permission||'';}
  setShare(id:number,permission:string){this.entryDraft.shares=this.entryDraft.shares.filter(s=>s.user_id!==id);if(permission)this.entryDraft.shares.push({user_id:id,permission:permission as 'view'|'edit'});}
  users=signal<User[]>([]);userModal=signal(false);editingUser:number|null=null;
  notificationContacts=signal<NotificationContact[]>([]);contactModal=signal(false);editingContact:number|null=null;contactDraft={name:'',email:'',channel:'email' as 'email'|'message',is_default:true};
  userDraft={name:'',email:'',password:'',role:'editor',workspace_ids:[] as number[]};
  spaceModal=signal(false);editingSpace:number|null=null;spaceDraft={name:'',color:'blue'};
  get spaceName(){return this.spaces().find(s=>s.id===this.spaceId())?.name||'Mi espacio';}
  roleName(role:string){return role==='admin'?'Administrador':role==='reader'?'Solo lectura':'Editor';}
  setDefaultEdit(value:boolean){this.defaultEdit.set(value);localStorage.setItem('nexo-default-edit',value?'yes':'no');}
  asset(id:any,preview=false,attachment=false){return '/api/'+(attachment?'attachments/':'media/')+id+'?workspace='+this.spaceId()+(preview?'&preview=1':'');}
  clearSpaceData(){this.generation++;this.tasks.set([]);this.counts.set({});this.clients.set([]);this.projects.set([]);this.notes.set([]);this.library.set([]);this.diagrams.set([]);this.notificationContacts.set([]);this.history.set([]);this.selected.set(null);this.selectedEntry.set(null);this.entryBlocks=[];this.taskBlocks=[];this.entryMedia.set([]);this.taskHistory.set([]);this.fullscreen.set(false);this.moveTask.set(null);this.catalogModal.set(false);this.query='';this.environment='';this.clientFilter='';this.projectFilter='';this.entryQuery='';this.entryClient='';this.entryProject='';this.entryCategory='';this.entryTag='';this.entryType='';this.archived=false;}
  async initializeSpaces(){const spaces=await firstValueFrom(this.http.get<Space[]>('/api/workspaces'));this.spaces.set(spaces);const saved=Number(localStorage.getItem('nexo-space'));const id=spaces.find(s=>s.id===saved)?.id||spaces[0]?.id;if(id){this.spaceId.set(id);localStorage.setItem('nexo-space',String(id));this.clearSpaceData();this.view.set('dashboard');await this.loadWorkspace();}else this.error.set('Tu cuenta todavía no tiene un espacio asignado.');}
  async switchSpace(id:number){if(id===this.spaceId()||this.busy()||this.pageLoading()||this.uploadBusy()||this.attachmentBusy())return;this.clearSpaceData();this.spaceId.set(Number(id));localStorage.setItem('nexo-space',String(id));this.view.set('dashboard');this.mobileOpen.set(false);this.error.set('');await this.loadWorkspace();this.notice.set('Ahora estás en '+this.spaceName+'.');}
  editSpace(s?:Space){this.editingSpace=s?.id||null;this.spaceDraft={name:s?.name||'',color:s?.color||'blue'};this.spaceModal.set(true);}
  async saveSpace(){if(this.busy())return;this.busy.set(true);try{const space=await firstValueFrom(this.editingSpace?this.http.put<Space>('/api/workspaces/'+this.editingSpace,this.spaceDraft):this.http.post<Space>('/api/workspaces',this.spaceDraft));this.spaces.set(await firstValueFrom(this.http.get<Space[]>('/api/workspaces')));this.spaceModal.set(false);this.notice.set('Espacio guardado: '+space.name+'.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  async loadUsers(){try{this.users.set(await firstValueFrom(this.http.get<User[]>('/api/users')));}catch(e){this.showError(e);}}
  editUser(u?:User){this.editingUser=u?.id||null;this.userDraft={name:u?.name||'',email:u?.email||'',password:'',role:u?.role||'editor',workspace_ids:u?.workspace_ids?[...u.workspace_ids]:[this.spaceId()]};this.userModal.set(true);}
  toggleUserSpace(id:number,checked:boolean){this.userDraft.workspace_ids=checked?[...this.userDraft.workspace_ids,id]:this.userDraft.workspace_ids.filter(x=>x!==id);}
  async saveUser(){if(this.busy())return;this.busy.set(true);try{const payload={...this.userDraft,password:this.userDraft.password||null};await firstValueFrom(this.editingUser?this.http.put('/api/users/'+this.editingUser,payload):this.http.post('/api/users',payload));this.userModal.set(false);this.userDraft.password='';await this.loadUsers();this.notice.set('Usuario guardado. Solo podrá acceder a los espacios que le asignaste.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  editNotificationContact(c?:NotificationContact){this.editingContact=c?.id||null;this.contactDraft={name:c?.name||'',email:c?.email||'',channel:c?.channel||'email',is_default:c?.is_default??true};this.contactModal.set(true);}
  async saveNotificationContact(){if(this.busy())return;this.busy.set(true);try{const payload={...this.contactDraft};await firstValueFrom(this.editingContact?this.http.put('/api/notification-contacts/'+this.editingContact,payload):this.http.post('/api/notification-contacts',payload));this.contactModal.set(false);await this.loadNotificationContacts();this.notice.set('Destinatario guardado para este espacio.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  async deleteNotificationContact(c:NotificationContact){if(this.busy()||!confirm(`¿Quitar a ${c.name} de los destinatarios guardados?`))return;this.busy.set(true);try{await firstValueFrom(this.http.delete('/api/notification-contacts/'+c.id));await this.loadNotificationContacts();this.notice.set('Destinatario quitado.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  readEntry(){const e=this.selectedEntry();if(e){this.openEntry(e);this.view.set('reading');}}

  private currentAutosaveSnapshot(){return JSON.stringify(this.view()==='detail'?{draft:this.draft,description:this.taskDescriptionBlocks,blocks:this.taskBlocks}:this.view()==='entry'?{draft:this.entryDraft,blocks:this.entryBlocks,diagram:this.entryDiagram,media:this.entryMedia().map(m=>m.id)}:{});}
  private async autosaveTick(){if(this.busy()||this.uploadBusy()||this.attachmentBusy()||!['detail','entry'].includes(this.view()))return;if(this.view()==='detail'&&!this.selected())return;if(this.view()==='entry'&&!this.selectedEntry())return;const title=this.view()==='detail'?this.draft.title:this.entryDraft.title;if(!title.trim())return;const snapshot=this.currentAutosaveSnapshot();if(!this.autosaveSnapshot){this.autosaveSnapshot=snapshot;return;}if(snapshot===this.autosaveSnapshot)return;this.view()==='detail'?await this.save(true):await this.saveEntry(true);}

  async ngOnInit() {
    this.initializeTheme();
    try {
      const result = await firstValueFrom(this.http.get<{user: User | null}>('/api/session'));
      this.user.set(result.user);
      if (result.user) await this.initializeSpaces();this.autosaveTimer=setInterval(()=>this.autosaveTick(),1800);
    } catch (e) { this.showError(e); } finally { this.ready.set(true); }
  }
  private showError(e: unknown) {
    let message = 'No se pudo completar la operación. Inténtalo nuevamente.';
    if (e instanceof HttpErrorResponse) {
      if (e.status === 401 || e.status === 419) {
        this.user.set(null); this.clearSpaceData(); this.view.set('board'); this.tasks.set([]); this.selected.set(null); this.password = '';
        message = 'Tu sesión terminó. Vuelve a iniciar sesión.';
        firstValueFrom(this.http.get('/api/session')).catch(() => {});
      } else if (e.status === 422) {
        message = Object.values(e.error.errors || {}).flat().join(' ') || 'Revisa los campos del formulario.';
      } else if (e.status === 429) message = 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.';
      else if (e.status === 413) message = 'El envío supera el tamaño permitido por el servidor. Adjunta menos archivos.';
      else if (e.status === 0) message = 'No hay conexión con el servidor. Revisa tu conexión y vuelve a intentarlo.';
      else if (e.status === 403) message = 'No tienes permiso para esta acción o espacio.';
      else if (e.status === 404) message = 'La tarea o el archivo ya no está disponible.';
    }
    this.error.set(message);
  }
  async login() {
    if (this.busy()) return;
    this.busy.set(true); this.error.set('');
    try {
      await firstValueFrom(this.http.get('/api/session'));
      const result = await firstValueFrom(this.http.post<{user: User}>('/api/login', {email: this.email, password: this.password}));
      this.user.set(result.user); this.password = ''; this.notice.set(''); await this.initializeSpaces();
    } catch (e) { this.showError(e); } finally { this.busy.set(false); }
  }
  async logout() {
    if (this.busy()) return;
    this.busy.set(true);
    try { await firstValueFrom(this.http.post('/api/logout', {})); this.user.set(null); this.clearSpaceData(); this.tasks.set([]); this.selected.set(null); this.draft = this.emptyDraft(); this.view.set('board'); this.error.set(''); this.notice.set(''); }
    catch (e) { this.showError(e); } finally { this.busy.set(false); }
  }
  async loadTasks() {
    const generation=this.generation;
    this.loading.set(true);
    try {
      const result = await firstValueFrom(this.http.get<{tasks: Task[]; counts: Record<string, number>}>('/api/tasks', {}));
      if(generation!==this.generation)return;this.tasks.set(result.tasks); this.counts.set(result.counts);
    } catch (e) { this.showError(e); } finally { this.loading.set(false); }
  }
  async filter(environment?: string) {
    if (environment !== undefined) this.environment = environment;
    this.view.set('board'); this.error.set(''); await this.loadTasks();
  }
  async clearFilters() { this.query = ''; this.environment = ''; this.clientFilter='';this.projectFilter=''; await this.filter(); }
  column(status: string) { return this.visibleTasks().filter(task => (this.groupBy()==='environment'?task.environment:task.status) === status); }
  complete(task: Task) { return (task.checklist || []).filter(step => step.done).length; }
  overdue(task: Task) { return !!task.due_date && task.status !== 'done' && task.due_date.slice(0, 10) < [new Date().getFullYear(), String(new Date().getMonth() + 1).padStart(2, '0'), String(new Date().getDate()).padStart(2, '0')].join('-'); }
  taskCode(id: number) { return `NX-${String(id).padStart(3, '0')}`; }
  bugCode(id:number){return `BUG-${String(id).padStart(3,'0')}`;}
  bugsBy(statuses:string[]){const q=this.query.toLocaleLowerCase();return this.tasks().filter(t=>(t.task_type||'task')==='bug'&&statuses.includes(t.status)&&(!this.taskTag||(t.tags||[]).includes(this.taskTag))&&(!q||(t.title+' '+(t.description||'')+' '+(t.tags||[]).join(' ')).toLocaleLowerCase().includes(q)));}
  newBug(){this.newTask('pending','bug');}
  newTask(status = 'pending',type:'task'|'bug'='task') {
    if(!this.canEdit()||this.pageLoading())return;
    this.selected.set(null); this.draft = {...this.emptyDraft(),notify_emails:this.defaultRecipients('email'),notify_message_emails:this.defaultRecipients('message'),task_type:type, status: this.groupBy()==='status'?status:'pending', environment:this.groupBy()==='environment'&&this.environmentLabels[status]?status:'backlog'};this.taskDescriptionBlocks=[{type:'text',text:''}]; this.taskBlocks=[{type:'text',text:''}]; this.draft.status=this.defaultStatus(this.draft.environment,this.draft.status); this.files = []; this.view.set('detail'); this.error.set(''); this.notice.set(''); window.scrollTo(0, 0);
  }
  async openTask(task: Task) {
    if (this.busy()) return;
    this.busy.set(true); this.error.set(''); this.notice.set('');
    try {
      this.pageLoading.set(true);this.selected.set(null);
      const full = await firstValueFrom(this.http.get<Task>(`/api/tasks/${task.id}`));
      this.selected.set(full);
      this.taskDescriptionBlocks=full.description_blocks?.length ? structuredClone(full.description_blocks) : [{type:'text',text:full.description||''}];
      this.taskBlocks=full.notes_blocks?.length ? structuredClone(full.notes_blocks) : [{type:'text',text:''}];
      const fields=full.notification_fields||['title','code','status'];
      this.draft = {task_type:full.task_type||'task',tags:(full.tags||[]).join(', '),is_fire:!!full.is_fire,notify_on_production:!!full.notify_on_production,notify_emails:(full.notify_emails||[]).join(', '),notification_message:full.notification_message||'¡Listo! 🚀 La tarea ya está en Producción.',notify_message_on_production:!!full.notify_message_on_production,notify_message_emails:(full.notify_message_emails||[]).join(', '),notification_message_short:full.notification_message_short||'',notify_include_client:fields.includes('client'),notify_include_project:fields.includes('project'),notify_include_title:fields.includes('title'),notify_include_description:fields.includes('description'),notify_include_code:fields.includes('code'),notify_include_status:fields.includes('status'),notify_include_checklist:fields.includes('checklist'),notify_include_attachments:fields.includes('attachments'),estimated_delivery_at:full.estimated_delivery_at?String(full.estimated_delivery_at).slice(0,16):'',client_id:full.client_id?String(full.client_id):'', project_id:full.project_id?String(full.project_id):'', sql_notes:full.sql_notes||'', title: full.title, description: full.description || '', status: full.status, environment: full.environment, priority: full.priority, checklist_text: (full.checklist || []).map(s => s.text).join('\n')};
      this.files = []; this.view.set(this.defaultEdit()&&this.canEdit()?'detail':'taskreading'); this.loadHistory(task.id); window.scrollTo(0, 0);
    } catch (e) { this.showError(e); } finally { this.busy.set(false);this.pageLoading.set(false); }
  }
  async back() { this.pageLoading.set(true);this.view.set('board'); this.error.set('');try{await this.loadTasks();}finally{this.pageLoading.set(false);} }
  selectFiles(event: Event) {
    const input = event.target as HTMLInputElement;
    this.files = Array.from(input.files || []); this.error.set('');
    if (this.files.length > 8 || this.files.some(f => f.size > 10 * 1024 * 1024)) {
      this.error.set('Selecciona hasta 8 archivos de máximo 10 MB cada uno.'); this.files = []; input.value = '';
    }
  }
  async save(auto=false) {
    if (this.busy() || this.uploadBusy()) return;
    this.busy.set(true); if(auto)this.autosaveState.set('saving');this.error.set(''); if(!auto)this.notice.set('');
    const data = new FormData();
    Object.entries(this.draft).forEach(([key, value]) => data.append(key, typeof value==='boolean'?(value?'1':'0'):value));
    if (auto) data.append('autosave', '1');
    this.files.forEach(file => data.append('files[]', file));
    this.taskDescriptionBlocks.forEach((b,i)=>Object.entries(b).forEach(([k,v])=>data.append(`description_blocks[${i}][${k}]`,String(v??''))));
    this.taskBlocks.forEach((b,i)=>Object.entries(b).forEach(([k,v])=>data.append(`notes_blocks[${i}][${k}]`,String(v??''))));
    const id = this.selected()?.id;
    if (id) data.append('_method', 'PUT');
    try {
      const task = await firstValueFrom(this.http.post<Task>(id ? `/api/tasks/${id}` : '/api/tasks', data));
      this.selected.set(task); this.files = [];
      const input = document.getElementById('files') as HTMLInputElement | null; if (input) input.value = '';
      this.autosaveSnapshot=this.currentAutosaveSnapshot();if(auto)this.autosaveState.set('saved');else this.notice.set(task.notification==='sent'?'Tarea guardada y correo enviado.':task.notification==='failed'?'Tarea guardada, pero el correo no pudo enviarse. Revisa la configuración SMTP.':id?'Cambios guardados.':'Tarea creada. Ya puedes marcar tus pasos de entrega.');
    } catch (e) { this.showError(e); } finally { this.busy.set(false); }
  }
  async move(task: Task, status: string) {
    if (this.busy() || status === task.status) return;
    this.busy.set(true); this.error.set('');
    try { if(!this.allowedStatuses(task.environment).some(s=>s[0]===status)){this.error.set('Ese estado no está disponible en '+this.environmentLabels[task.environment]+'. Cambia primero el ambiente.');return;} const result=await firstValueFrom(this.http.patch<Task>(`/api/tasks/${task.id}/status`, {status})); await this.loadTasks(); this.notice.set(result.notification==='sent'?'Estado actualizado y correo enviado.':result.notification==='failed'?'Estado actualizado, pero falló el envío del correo.':'Estado actualizado.'); }
    catch (e) { this.showError(e); } finally { this.busy.set(false); }
  }
  async toggleStep(index: number) {
    const task = this.selected(); if (!task || this.busy()) return;
    this.busy.set(true); this.error.set('');
    try { this.selected.set(await firstValueFrom(this.http.patch<Task>(`/api/tasks/${task.id}/checklist`, {index, done: !task.checklist[index].done}))); }
    catch (e) { this.showError(e); } finally { this.busy.set(false); }
  }
  async deleteFile(file: Attachment) {
    if (this.busy() || !window.confirm(`¿Eliminar ${file.name}?`)) return;
    this.busy.set(true); this.error.set('');
    try { await firstValueFrom(this.http.delete(`/api/attachments/${file.id}`)); this.selected.update(t => t ? {...t, attachments: t.attachments?.filter(f => f.id !== file.id)} : t); this.notice.set('Archivo eliminado.'); }
    catch (e) { this.showError(e); } finally { this.busy.set(false); }
  }
  async deleteTask() {
    const task = this.selected();
    if (!task || this.busy() || !window.confirm('¿Eliminar la tarea y todos sus adjuntos? Esta acción no se puede deshacer.')) return;
    this.busy.set(true); this.error.set('');
    try { await firstValueFrom(this.http.delete(`/api/tasks/${task.id}`)); this.selected.set(null); this.notice.set('Tarea eliminada.'); await this.back(); }
    catch (e) { this.showError(e); } finally { this.busy.set(false); }
  }
}
