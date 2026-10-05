import { Component, inject, signal, computed, OnInit, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { assetUrl, isNativeMobile, MOBILE_API_ORIGIN } from './mobile';
import {Block, Media, NoteEditorComponent, ModalComponent} from './editor';
import {Diagram,DiagramComponent,emptyDiagram} from './diagram';
import {ReadingComponent} from './reading';
import {IconComponent} from './icons';
import {ButtonHintDirective} from './button-hint';

type Catalog={id:number;name:string;code?:string;client_id?:number|null;description?:string};
type Activity={id:number;action:string;subject_type:string;subject_id:number;title:string;created_at:string};
type AuditLog={id:number;workspace_id:number|null;workspace_name:string|null;actor_id:number|null;actor_name:string|null;actor_email:string|null;event:string;action:string;subject_type:string|null;subject_id:number|null;subject_name:string|null;metadata:Record<string,unknown>|string|null;ip_address:string|null;user_agent:string|null;created_at:string};
type Share={user_id:number;name?:string;email?:string;permission:'view'|'edit'};
type Member={id:number;name:string;email:string};
type Entry={diagram?:Diagram;id:number;kind:'note'|'library'|'diagram';title:string;blocks:Block[];search_text:string;client_id:number|null;project_id:number|null;category:string;tags:string[];color:string;pinned:boolean;archived:boolean;media:Media[];updated_at:string;visibility?:'private'|'workspace'|'shared';shares?:Share[];can_edit?:boolean};
type User = {id: number; name: string; email: string;role:'admin'|'editor'|'reader';profile_photo_url?:string|null;two_factor_enabled?:boolean;workspace_ids?:number[]};
type Space={id:number;name:string;color:string};
type NotificationContact={id:number;name:string;email:string;channel:'email'|'message';is_default:boolean};
type Attachment = {id: number; name: string; size: number};
type Step = {text: string; done: boolean};
type StatusSetting = {visible:boolean;active:boolean};
type WorkflowStatus = {key:string;label:string;active:boolean};
type WorkflowEnvironment = {key:string;label:string;active:boolean;allowed_statuses:string[]};
type WorkflowConfig = {statuses:WorkflowStatus[];environments:WorkflowEnvironment[]};
const defaultWorkflow:WorkflowConfig={statuses:[{key:'pending',label:'Pendiente',active:true},{key:'development',label:'En desarrollo',active:true},{key:'review',label:'En revisión',active:true},{key:'done',label:'Completada',active:true}],environments:[{key:'backlog',label:'Backlog',active:true,allowed_statuses:['pending']},{key:'local',label:'Local',active:true,allowed_statuses:['development','review','done']},{key:'development',label:'Desarrollo',active:true,allowed_statuses:['development','review','done']},{key:'qa',label:'QA',active:true,allowed_statuses:['review','done']},{key:'certification',label:'Certificación',active:true,allowed_statuses:['review','done']},{key:'production',label:'Producción',active:true,allowed_statuses:['review','done']}]};
type Task = {task_type:'task'|'bug';tags?:string[];is_fire:boolean;notify_on_production:boolean;notify_emails?:string[];email_notification_events?:string[];notify_message_on_production:boolean;notify_message_emails?:string[];notification_message?:string;notification_message_short?:string;notification_fields?:string[];notification?:string;environment_return_from?:string|null;environment_return_to?:string|null;environment_return_reason?:string|null;environment_return_solution?:string|null;environment_return_resolved?:boolean;environment_returned_at?:string|null;production_return_reason?:string|null;production_returned_at?:string|null;client_id?:number|null; project_id?:number|null; notes_blocks?:Block[];description_blocks?:Block[]; sql_notes?:string|null; id: number; title: string; description: string | null; status: string; environment: string; priority: string; due_date: string | null; estimated_delivery_at?: string | null; checklist: Step[]; attachments?: Attachment[]; attachments_count?: number; updated_at: string; created_at: string};
type Draft = {task_type:'task'|'bug';tags:string;is_fire:boolean;notify_on_production:boolean;notify_emails:string;email_notification_events:string[];notification_message:string;notify_message_on_production:boolean;notify_message_emails:string;notification_message_short:string;notify_include_client:boolean;notify_include_project:boolean;notify_include_title:boolean;notify_include_description:boolean;notify_include_code:boolean;notify_include_status:boolean;notify_include_checklist:boolean;notify_include_attachments:boolean;environment_return_resolved:boolean;environment_return_solution:string;estimated_delivery_at:string;client_id:string; project_id:string; sql_notes:string; title: string; description: string; status: string; environment: string; priority: string; checklist_text: string};

@Component({selector: 'app-root', standalone: true, imports: [CommonModule, FormsModule, NoteEditorComponent, ModalComponent, IconComponent, DiagramComponent, ReadingComponent, ButtonHintDirective], templateUrl: './workspace.html'})
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
  passwordRecoveryUrl=isNativeMobile?`${MOBILE_API_ORIGIN}/password/forgot`:'/password/forgot';
  loginCaptcha=signal<{id:string;question:string}|null>(null);loginCaptchaAnswer='';
  twoFactorRequired=signal(false);twoFactorCode='';
  draft: Draft = this.emptyDraft();
  workflow=signal<WorkflowConfig>(structuredClone(defaultWorkflow));workflowDirty=signal(false);workflowStatusName='';workflowEnvironmentName='';
  get statusLabels():Record<string,string>{return Object.fromEntries(this.workflow().statuses.map(status=>[status.key,status.label]));}
  get environmentLabels():Record<string,string>{return Object.fromEntries(this.workflow().environments.map(environment=>[environment.key,environment.label]));}
  priorityLabels: Record<string, string> = {low: 'Baja', normal: 'Normal', high: 'Alta', urgent: 'Urgente'};
  dashboardStatOptions=[{key:'pending',label:'Tareas pendientes',icon:'columns',tone:'blue',description:'Pendientes fuera del Backlog'},{key:'development',label:'En desarrollo',icon:'code',tone:'violet',description:'Trabajo en curso'},{key:'review',label:'En revisión',icon:'history',tone:'amber',description:'Esperando validación'},{key:'done',label:'Completadas',icon:'check',tone:'teal',description:'Entregas realizadas'},{key:'backlog',label:'Backlog',icon:'folder',tone:'slate',description:'Pendientes por priorizar'}];
  statusOrder=signal<string[]>(defaultWorkflow.statuses.map(status=>status.key));
  statusSettings=signal<Record<string,StatusSetting>>({});
  dashboardStatVisibility=signal<Record<string,boolean>>({});
  dashboardEnvironment='';
  dashboardDefaultEnvironment='';
  get dashboardEnvironments(){return this.environments.filter(([key])=>this.isEnvironmentActive(key)||this.tasks().some(task=>task.environment===key)||this.dashboardEnvironment===key);}
  get statuses(){return this.statusOrder().filter(key=>key in this.statusLabels).map(key=>[key,this.statusLabels[key]] as [string,string]);}
  get environments(){return this.workflow().environments.filter(environment=>environment.active||this.tasks().some(task=>task.environment===environment.key)||this.draft.environment===environment.key||this.moveEnvironment===environment.key).map(environment=>[environment.key,environment.label] as [string,string]);}
  priorities = Object.entries(this.priorityLabels);
  total = computed(() => Object.values(this.counts()).reduce((sum, n) => sum + n, 0));
  private emptyDraft(): Draft { return {task_type:'task',tags:'',is_fire:false,notify_on_production:false,notify_emails:'',email_notification_events:['production_completed'],notification_message:'¡Listo! 🚀 La tarea ya está en Producción.',notify_message_on_production:false,notify_message_emails:'',notification_message_short:'',notify_include_client:true,notify_include_project:true,notify_include_title:true,notify_include_description:true,notify_include_code:true,notify_include_status:true,notify_include_checklist:true,notify_include_attachments:false,environment_return_resolved:false,environment_return_solution:'',estimated_delivery_at:'',client_id:'', project_id:'', sql_notes:'', title: '', description: '', status: 'pending', environment: 'local', priority: 'normal', checklist_text: ''}; }
  get emailEnvironmentOptions(){return Object.entries(this.environmentLabels);}
  toggleEmailEvent(key:string,event:Event){const checked=(event.target as HTMLInputElement).checked;const events=this.draft.email_notification_events||[];this.draft.email_notification_events=checked?[...new Set([...events,key])]:events.filter(item=>item!==key);}


  clients=signal<Catalog[]>([]); projects=signal<Catalog[]>([]);
  notes=signal<Entry[]>([]); library=signal<Entry[]>([]); history=signal<Activity[]>([]); taskHistory=signal<Activity[]>([]);
  historyPage=1; historyLast=1;
  auditLogs=signal<AuditLog[]>([]);auditPage=1;auditLast=1;auditTotal=0;auditQuery='';auditEvent='';auditActor='';auditWorkspace='';auditFrom='';auditTo='';auditEvents=[['task.created','Tarea creada'],['task.updated','Tarea actualizada'],['task.deleted','Tarea eliminada'],['task.moved','Tarea movida'],['task.status.changed','Estado de tarea cambiado'],['task.checklist.updated','Lista actualizada'],['task.attachment.deleted','Adjunto eliminado'],['task.notification.sent','Aviso enviado'],['note.created','Nota creada'],['note.updated','Nota actualizada'],['note.deleted','Nota eliminada'],['library.created','Recurso agregado'],['library.updated','Recurso actualizado'],['library.deleted','Recurso eliminado'],['diagram.created','Diagrama creado'],['diagram.updated','Diagrama actualizado'],['diagram.deleted','Diagrama eliminado'],['auth.login','Inicio de sesión'],['auth.logout','Cierre de sesión'],['security.password.changed','Contraseña actualizada'],['security.password.reset','Contraseña recuperada'],['security.two_factor.enabled','Doble factor activado'],['security.two_factor.disabled','Doble factor desactivado'],['account.profile_photo.updated','Foto actualizada'],['account.profile_photo.deleted','Foto quitada'],['admin.user.created','Usuario agregado'],['admin.user.updated','Usuario actualizado'],['admin.workspace.created','Espacio creado'],['admin.workspace.updated','Espacio actualizado'],['admin.notification_contact.created','Destinatario agregado'],['admin.notification_contact.updated','Destinatario actualizado'],['admin.notification_contact.deleted','Destinatario quitado']];
  taskBlocks:Block[]=[];taskDescriptionBlocks:Block[]=[]; entryBlocks:Block[]=[];
  uploadBusy=signal(false); attachmentBusy=signal(false);
  theme=signal(localStorage.getItem('flujo-theme')||'light');palette=signal(localStorage.getItem('nexo-theme-palette')||'nexo');themeOptions=[{key:'nexo',label:'Nexo',description:'Azul índigo, el estilo original'},{key:'ocean',label:'Océano',description:'Azules profundos y frescos'},{key:'forest',label:'Bosque',description:'Verdes tranquilos'},{key:'violet',label:'Violeta',description:'Morados suaves'},{key:'sunset',label:'Atardecer',description:'Tonos cálidos y claros'}];collapsed=signal(localStorage.getItem('flujo-sidebar')==='collapsed'); mobileOpen=signal(false);
  groupBy=signal(localStorage.getItem('flujo-group')||'environment'); autoEnvironment=signal(localStorage.getItem('flujo-auto-environment')==='yes');
  clientFilter='';projectFilter='';entryQuery='';entryClient='';entryProject='';entryCategory='';entryTag='';entryType='';archived=false;
  activeKind:'note'|'library'|'diagram'='note'; librarySection:'library'|'cheatsheets'='library'; selectedEntry=signal<Entry|null>(null); entryMedia=signal<Media[]>([]);
  entryDraft={title:'',color:'yellow',pinned:false,archived:false,client_id:'',project_id:'',category:'',tags:'',visibility:'private' as 'private'|'workspace'|'shared',shares:[] as Share[]};
  colors=[['yellow','Amarillo'],['blue','Azul'],['green','Verde'],['red','Rojo'],['purple','Morado'],['gray','Gris']];
  catalogKind:'clients'|'projects'='clients';catalogId:number|null=null;catalogDraft={name:'',code:'',description:'',client_id:''};catalogModal=signal(false);
  moveTask=signal<Task|null>(null);moveEnvironment='local';moveStatus='development';moveReason='';dragOver=signal('');
  previewMedia=signal<Media|null>(null);
  writingImprove=signal<{title:string;description:string;originalTitle:string;originalDescription:string}|null>(null);
  statusDraft=signal<{subject:string;body:string}|null>(null);
  nav=[['dashboard','grid','Inicio'],['board','columns','Tablero'],['notes','note','Notas importantes'],['library','folder','Biblioteca'],['cheatsheets','file','Cheat sheets'],['diagrams','layers','Diagramas']];
  management=[['clients','users','Clientes'],['projects','layers','Proyectos'],['history','history','Historial']];
  sidebarPreferences=[{key:'dashboard',label:'Inicio',icon:'grid',description:'Resumen del espacio'},{key:'board',label:'Tablero',icon:'columns',description:'Tareas y estados'},{key:'notes',label:'Notas importantes',icon:'note',description:'Ideas y listas personales'},{key:'library',label:'Biblioteca',icon:'folder',description:'Archivos y referencias'},{key:'cheatsheets',label:'Cheat sheets',icon:'file',description:'PDF e imágenes de consulta'},{key:'diagrams',label:'Diagramas',icon:'layers',description:'Pizarras y diagramas'},{key:'clients',label:'Clientes',icon:'users',description:'Directorio de clientes'},{key:'projects',label:'Proyectos',icon:'layers',description:'Proyectos por cliente'},{key:'history',label:'Historial',icon:'history',description:'Actividad reciente'},{key:'audit',label:'Auditoría',icon:'history',description:'Registro de cambios y accesos',adminOnly:true},{key:'spaces',label:'Espacios',icon:'layers',description:'Administrar espacios',adminOnly:true},{key:'users',label:'Usuarios',icon:'users',description:'Personas y permisos',adminOnly:true}];
  sidebarVisibility=signal<Record<string,boolean>>({});
  pageNames:Record<string,string>={dashboard:'Inicio',board:'Tablero',notes:'Notas importantes',library:'Biblioteca',cheatsheets:'Cheat sheets',clients:'Clientes',projects:'Proyectos',history:'Historial',audit:'Auditoría',diagrams:'Diagramas',users:'Usuarios',spaces:'Espacios',reading:'Lectura',taskreading:'Lectura de tarea',settings:'Preferencias',detail:'Requerimiento',entry:'Editor'};
  get currentTitle(){return this.pageNames[this.view()]||'Mi espacio';}
  private sidebarPreferenceStorageKey(userId:number){return `nexo-sidebar-menu:${userId}`;}
  private themeModeStorageKey(userId:number){return `nexo-theme-mode:${userId}`;}
  private themePaletteStorageKey(userId:number){return `nexo-theme-palette:${userId}`;}
  loadAppearancePreferences(userId:number){const mode=localStorage.getItem(this.themeModeStorageKey(userId))||localStorage.getItem('flujo-theme')||'light';this.theme.set(mode==='dark'?'dark':'light');const saved=localStorage.getItem(this.themePaletteStorageKey(userId))||localStorage.getItem('nexo-theme-palette')||'nexo';this.palette.set(this.themeOptions.some(option=>option.key===saved)?saved:'nexo');this.initializeTheme();}
  setThemePalette(key:string){if(!this.themeOptions.some(option=>option.key===key))return;this.palette.set(key);const userId=this.user()?.id;localStorage.setItem(userId?this.themePaletteStorageKey(userId):'nexo-theme-palette',key);this.initializeTheme();}
  loadSidebarPreferences(userId:number){let stored:Record<string,boolean>={};try{stored=JSON.parse(localStorage.getItem(this.sidebarPreferenceStorageKey(userId))||'{}')||{};}catch{}this.sidebarVisibility.set(Object.fromEntries(this.sidebarPreferences.map(item=>[item.key,stored[item.key]!==false])));}
  sidebarItemVisible(key:string){return this.sidebarVisibility()[key]!==false;}
  setSidebarItemVisible(key:string,visible:boolean){const next={...this.sidebarVisibility(),[key]:visible};this.sidebarVisibility.set(next);const userId=this.user()?.id;if(userId)localStorage.setItem(this.sidebarPreferenceStorageKey(userId),JSON.stringify(next));}
  resetSidebarPreferences(){const defaults=Object.fromEntries(this.sidebarPreferences.map(item=>[item.key,true]));this.sidebarVisibility.set(defaults);const userId=this.user()?.id;if(userId)localStorage.setItem(this.sidebarPreferenceStorageKey(userId),JSON.stringify(defaults));}
  visibleNav(){return this.nav.filter(([key])=>this.sidebarItemVisible(key));}
  visibleManagement(){return this.management.filter(([key])=>this.sidebarItemVisible(key));}
  visibleAdminItems(){return this.isAdmin()?[['audit','history','Auditoría'],['spaces','layers','Espacios'],['users','users','Usuarios']].filter(([key])=>this.sidebarItemVisible(key)):[];}
  private dashboardStatPreferenceStorageKey(userId:number){return `nexo-dashboard-stats:${userId}`;}
  loadDashboardStatPreferences(userId:number){let stored:Record<string,boolean>={};try{stored=JSON.parse(localStorage.getItem(this.dashboardStatPreferenceStorageKey(userId))||'{}')||{};}catch{}this.dashboardStatVisibility.set(Object.fromEntries(this.dashboardStatOptions.map(option=>[option.key,stored[option.key]!==false])));let environment='';try{environment=localStorage.getItem(`nexo-dashboard-environment:${userId}`)||'';}catch{}this.dashboardDefaultEnvironment=Object.hasOwn(this.environmentLabels,environment)?environment:'';this.dashboardEnvironment=this.dashboardDefaultEnvironment;}
  setDashboardDefaultEnvironment(environment:string){this.dashboardDefaultEnvironment=Object.hasOwn(this.environmentLabels,environment)?environment:'';this.dashboardEnvironment=this.dashboardDefaultEnvironment;const userId=this.user()?.id;if(userId)localStorage.setItem(`nexo-dashboard-environment:${userId}`,this.dashboardDefaultEnvironment);}
  dashboardStatVisible(key:string){return this.dashboardStatVisibility()[key]!==false;}
  visibleDashboardStats(){return this.dashboardStatOptions.filter(option=>this.dashboardStatVisible(option.key));}
  setDashboardStatVisible(key:string,visible:boolean){this.dashboardStatVisibility.update(settings=>({...settings,[key]:visible}));this.saveDashboardStatPreferences();}
  resetDashboardStatPreferences(){this.dashboardStatVisibility.set(Object.fromEntries(this.dashboardStatOptions.map(option=>[option.key,true])));this.saveDashboardStatPreferences();}
  private saveDashboardStatPreferences(){const userId=this.user()?.id;if(userId)localStorage.setItem(this.dashboardStatPreferenceStorageKey(userId),JSON.stringify(this.dashboardStatVisibility()));}
  dashboardStatCount(key:string){const tasks=this.tasks().filter(task=>!this.dashboardEnvironment||task.environment===this.dashboardEnvironment);if(key==='backlog')return tasks.filter(task=>task.environment==='backlog').length;if(key==='pending')return tasks.filter(task=>task.status==='pending'&&task.environment!=='backlog').length;return tasks.filter(task=>task.status===key).length;}
  private statusPreferenceStorageKey(userId:number,spaceId:number){return `nexo-status-board:${userId}:${spaceId}`;}
  loadStatusPreferences(userId:number,spaceId:number){let stored:{settings?:Record<string,Partial<StatusSetting>>}={};try{stored=JSON.parse(localStorage.getItem(this.statusPreferenceStorageKey(userId,spaceId))||'{}')||{};}catch{}const known=Object.keys(this.statusLabels);this.statusOrder.set(known);this.statusSettings.set(Object.fromEntries(known.map(key=>[key,{visible:stored.settings?.[key]?.visible!==false,active:this.isStatusActive(key)}])));}
  private saveStatusPreferences(){const userId=this.user()?.id,spaceId=this.spaceId();if(!userId||!spaceId)return;localStorage.setItem(this.statusPreferenceStorageKey(userId,spaceId),JSON.stringify({settings:this.statusSettings()}));}
  statusSetting(key:string){return {...(this.statusSettings()[key]||{visible:true,active:true}),active:this.isStatusActive(key)};}
  statusTaskCount(key:string){return this.tasks().filter(task=>task.status===key).length;}
  setStatusSetting(key:string,field:'visible'|'active',value:boolean){if(field==='active'){this.setWorkflowStatusActive(key,value);return;}this.statusSettings.update(settings=>({...settings,[key]:{...this.statusSetting(key),visible:value}}));this.saveStatusPreferences();}
  moveStatusOrder(key:string,direction:-1|1){this.moveWorkflowItem('status',key,direction);}
  resetStatusPreferences(){this.statusSettings.set(Object.fromEntries(Object.keys(this.statusLabels).map(key=>[key,{visible:true,active:this.isStatusActive(key)}])));this.saveStatusPreferences();}
  workflowStatus(key:string){return this.workflow().statuses.find(status=>status.key===key);}
  workflowEnvironment(key:string){return this.workflow().environments.find(environment=>environment.key===key);}
  isStatusActive(key:string){return this.workflowStatus(key)?.active===true;}
  isEnvironmentActive(key:string){return this.workflowEnvironment(key)?.active===true;}
  allowedStatuses(env:string,currentStatus?:string){const allowed=this.workflowEnvironment(env)?.allowed_statuses||[];return this.statuses.filter(([key])=>allowed.includes(key)&&(this.isStatusActive(key)||key===currentStatus));}
  defaultStatus(env:string,wanted='development'){const allowed=this.allowedStatuses(env),preferred=env==='backlog'?'pending':'development';return allowed.some(s=>s[0]===wanted)?wanted:allowed.some(s=>s[0]===preferred)?preferred:allowed[0]?.[0]||wanted;}
  private workflowCode(value:string,prefix:string,used:string[]){const base=value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'').slice(0,32)||prefix;let key=base,index=2;while(used.includes(key))key=`${base}_${index++}`;return key;}
  private updateWorkflow(update:(workflow:WorkflowConfig)=>void){const workflow=structuredClone(this.workflow());update(workflow);this.workflow.set(workflow);this.statusOrder.set(workflow.statuses.map(status=>status.key));this.statusSettings.update(settings=>Object.fromEntries(workflow.statuses.map(status=>[status.key,{visible:settings[status.key]?.visible!==false,active:status.active}])));this.workflowDirty.set(true);}
  addWorkflowStatus(){const label=this.workflowStatusName.trim();if(!label)return;this.updateWorkflow(workflow=>workflow.statuses.push({key:this.workflowCode(label,'estado',workflow.statuses.map(status=>status.key)),label,active:true}));this.workflowStatusName='';}
  addWorkflowEnvironment(){const label=this.workflowEnvironmentName.trim();if(!label)return;this.updateWorkflow(workflow=>{const key=this.workflowCode(label,'ambiente',workflow.environments.map(environment=>environment.key));const first=workflow.statuses.find(status=>status.active)?.key||workflow.statuses[0]?.key;workflow.environments.push({key,label,active:true,allowed_statuses:first?[first]:[]});});this.workflowEnvironmentName='';}
  setWorkflowStatusActive(key:string,active:boolean){this.updateWorkflow(workflow=>{const status=workflow.statuses.find(item=>item.key===key);if(status)status.active=active;});}
  setWorkflowEnvironmentActive(key:string,active:boolean){this.updateWorkflow(workflow=>{const environment=workflow.environments.find(item=>item.key===key);if(environment)environment.active=active;});}
  setWorkflowStatusLabel(key:string,label:string){this.updateWorkflow(workflow=>{const status=workflow.statuses.find(item=>item.key===key);if(status)status.label=label.slice(0,60);});}
  setWorkflowEnvironmentLabel(key:string,label:string){this.updateWorkflow(workflow=>{const environment=workflow.environments.find(item=>item.key===key);if(environment)environment.label=label.slice(0,60);});}
  setEnvironmentAllowedStatus(environmentKey:string,statusKey:string,allowed:boolean){this.updateWorkflow(workflow=>{const environment=workflow.environments.find(item=>item.key===environmentKey);if(!environment)return;environment.allowed_statuses=allowed?[...new Set([...environment.allowed_statuses,statusKey])]:environment.allowed_statuses.filter(key=>key!==statusKey);});}
  moveWorkflowItem(type:'status'|'environment',key:string,direction:-1|1){this.updateWorkflow(workflow=>{const items=type==='status'?workflow.statuses:workflow.environments;const index=items.findIndex(item=>item.key===key),next=index+direction;if(index<0||next<0||next>=items.length)return;[items[index],items[next]]=[items[next],items[index]];});}
  async loadWorkflow(){try{const workflow=await firstValueFrom(this.http.get<WorkflowConfig>('/api/workflow'));this.workflow.set(workflow);this.statusOrder.set(workflow.statuses.map(status=>status.key));this.workflowDirty.set(false);}catch(e){this.showError(e);}}
  async saveWorkflow(){if(!this.isAdmin()||this.busy()||!this.workflowDirty())return;this.busy.set(true);this.error.set('');try{const workflow=await firstValueFrom(this.http.put<WorkflowConfig>('/api/workflow',this.workflow()));this.workflow.set(workflow);this.statusOrder.set(workflow.statuses.map(status=>status.key));this.workflowDirty.set(false);const user=this.user();if(user)this.loadStatusPreferences(user.id,this.spaceId());await this.loadTasks();this.notice.set('Configuración del flujo guardada. Tus tarjetas se conservaron.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  canUseStatus(env:string,status:string,currentStatus?:string){return this.allowedStatuses(env,currentStatus).some(s=>s[0]===status);}
  taskEnvironmentChanged(){this.draft.status=this.defaultStatus(this.draft.environment,this.draft.status);}
  get columns(){
    if(this.groupBy()==='environment')return this.environment?this.environments.filter(([key])=>key===this.environment):this.environments;
    const statuses=this.statuses.filter(([key])=>this.statusSetting(key).visible||this.tasks().some(task=>task.status===key));
    return this.environment==='backlog'?statuses.filter(([key])=>key==='pending'):statuses;
  }
  get today(){return new Date().toLocaleDateString('es-CL',{weekday:'long',day:'numeric',month:'long'});}
  get pending(){return this.total()-(this.counts()['done']||0);}
  clientName(id?:number|null){const c=this.clients().find(c=>c.id===id);return c?c.name+(c.code?' · '+c.code:''):'General';}
  projectName(id?:number|null){return this.projects().find(c=>c.id===id)?.name||'';}
  formattedDescription(task:Task):Block[]{return task.description_blocks?.length?task.description_blocks:[{type:'text',text:task.description||''}];}
  projectOptions(client:string){return this.projects().filter(p=>String(p.client_id||'')===client);}
  visibleTasks(){const q=this.query.toLocaleLowerCase();return this.tasks().filter(t=>(!this.environment||t.environment===this.environment)&&(!this.clientFilter||String(t.client_id)===this.clientFilter)&&(!this.projectFilter||String(t.project_id)===this.projectFilter)&&(!this.taskTag||(t.tags||[]).includes(this.taskTag))&&(!q||(t.title+' '+(t.description||'')+' '+(t.tags||[]).join(' ')+' '+this.clientName(t.client_id)).toLocaleLowerCase().includes(q)));}
  taskTags(){return [...new Set(this.tasks().flatMap(t=>t.tags||[]))].sort();}
  visibleEntries(){const q=this.entryQuery.toLocaleLowerCase();const list=this.view()==='library'||this.view()==='cheatsheets'?this.library():this.view()==='diagrams'?this.diagrams():this.notes();return list.filter(e=>(this.view()!=='cheatsheets'||e.category==='Cheat sheets')&&(!q||(e.title+' '+e.search_text+' '+e.tags.join(' ')).toLocaleLowerCase().includes(q))&&(!this.entryClient||String(e.client_id)===this.entryClient)&&(!this.entryProject||String(e.project_id)===this.entryProject)&&(!this.entryCategory||e.category===this.entryCategory)&&(!this.entryTag||e.tags.includes(this.entryTag))&&(!this.entryType||e.media.some(m=>this.fileType(m)===this.entryType)));}
  fileType(m:Media){const name=m.name.toLowerCase(),ext=name.includes('.')?name.split('.').pop()||'':'';if(['image/png','image/jpeg','image/webp','image/gif'].includes(m.mime)||['png','jpg','jpeg','gif','webp'].includes(ext))return'image';if(m.mime.startsWith('video/')||['mp4','mov','m4v','webm','avi','mkv','mpeg','mpg','3gp'].includes(ext))return'video';if(m.mime.startsWith('audio/')||['mp3','m4a','aac','ogg','wav','flac','wma'].includes(ext))return'audio';if(m.mime==='application/pdf'||ext==='pdf')return'pdf';if(['apk','aab','xapk'].includes(ext)||m.mime==='application/vnd.android.package-archive')return'apk';if(ext==='sql')return'sql';if(['zip','rar','7z','tar','gz','bz2','xz'].includes(ext))return'archive';if(['txt','csv','md','rtf','odt','ods','odp','doc','docx','xls','xlsx','ppt','pptx','json','xml','yaml','yml','log','ini','conf'].includes(ext))return'document';return'other';}
  fileTypeLabel(m:Media){return({image:'Imagen',video:'Video',audio:'Audio',pdf:'PDF',apk:'APK / Android',sql:'SQL',archive:'Comprimido',document:'Documento',other:'Otro archivo'} as Record<string,string>)[this.fileType(m)];}
  fileIcon(m:Media){return({image:'image',video:'video',audio:'audio',sql:'code',archive:'archive',apk:'android'} as Record<string,string>)[this.fileType(m)]||'file';}
  formatFileSize(size:number){return size>=1048576?`${(size/1048576).toFixed(1)} MB`:`${(size/1024).toFixed(1)} KB`;}
  isImageName(name:string){return /\.(png|jpe?g|webp|gif)$/i.test(name);}
  firstImage(e:Entry){return e.blocks.find(b=>b.type==='image')?.media_id;}
  excerpt(e:Entry){return (e.search_text||'').slice(0,180);}
  entryCategories(){return [...new Set(this.library().map(e=>e.category).filter(Boolean))];}
  entryTags(){return [...new Set(this.library().flatMap(e=>e.tags))];}
  themeColor(key:string){return ({nexo:'#5266eb',ocean:'#1688b6',forest:'#27845b',violet:'#7956c9',sunset:'#c46b3d'} as Record<string,string>)[key]||'#5266eb';}
  initializeTheme(){document.documentElement.dataset['theme']=this.theme();document.documentElement.dataset['palette']=this.palette();}
  toggleTheme(){this.theme.set(this.theme()==='light'?'dark':'light');const userId=this.user()?.id;localStorage.setItem(userId?this.themeModeStorageKey(userId):'flujo-theme',this.theme());this.initializeTheme();}
  toggleSidebar(){this.collapsed.update(v=>!v);localStorage.setItem('flujo-sidebar',this.collapsed()?'collapsed':'expanded');}
  setGroup(value:string){this.groupBy.set(value);localStorage.setItem('flujo-group',value);}
  setAuto(value:boolean){this.autoEnvironment.set(value);localStorage.setItem('flujo-auto-environment',value?'yes':'no');}
  async loadWorkspace(){this.pageLoading.set(true);try{await Promise.all([this.loadTasks(),this.loadCatalog(),this.loadEntries('note'),this.loadEntries('library'),this.loadEntries('diagram'),this.loadHistory(),this.loadNotificationContacts()]);}finally{this.pageLoading.set(false);}}
  async loadNotificationContacts(){try{this.notificationContacts.set(await firstValueFrom(this.http.get<NotificationContact[]>('/api/notification-contacts')));}catch(e){this.showError(e);}}
  defaultRecipients(channel:'email'|'message'){return this.notificationContacts().filter(c=>c.channel===channel&&c.is_default).map(c=>c.email).join(', ');}
  async loadCatalog(){const generation=this.generation;try{const c=await firstValueFrom(this.http.get<{clients:Catalog[];projects:Catalog[]}>('/api/catalog'));if(generation!==this.generation)return;this.clients.set(c.clients);this.projects.set(c.projects);}catch(e){this.showError(e);}}
  async loadEntries(kind:'note'|'library'|'diagram'){const generation=this.generation;try{const entries=await firstValueFrom(this.http.get<Entry[]>('/api/entries',{params:{kind,archived:this.archived?'1':'0'}}));if(generation!==this.generation)return;(kind==='note'?this.notes:kind==='diagram'?this.diagrams:this.library).set(entries);}catch(e){this.showError(e);}}
  async loadHistory(taskId?:number,page=1){const generation=this.generation;try{const data=await firstValueFrom(this.http.get<{data:Activity[];last_page:number}>('/api/history',{params:taskId?{task_id:taskId}:{page}}));if(generation!==this.generation)return;if(taskId)this.taskHistory.set(data.data);else{this.history.set(data.data);this.historyPage=page;this.historyLast=data.last_page;}}catch(e){this.showError(e);}}
  private auditParams():Record<string,string>{return Object.fromEntries(Object.entries({q:this.auditQuery,event:this.auditEvent,actor_id:this.auditActor,workspace_id:this.auditWorkspace,from:this.auditFrom,to:this.auditTo}).filter(([,value])=>!!value));}
  async loadAuditLogs(page=1){if(!this.isAdmin())return;try{const result=await firstValueFrom(this.http.get<{data:AuditLog[];current_page:number;last_page:number;total:number}>('/api/audit-logs',{params:{...this.auditParams(),page:String(page)}}));this.auditLogs.set(result.data);this.auditPage=result.current_page;this.auditLast=result.last_page;this.auditTotal=result.total;}catch(e){this.showError(e);}}
  async exportAuditLogs(){if(!this.isAdmin()||this.busy())return;this.busy.set(true);try{const blob=await firstValueFrom(this.http.get('/api/audit-logs/export',{params:this.auditParams(),responseType:'blob'}));const url=URL.createObjectURL(blob);const link=document.createElement('a');link.href=url;link.download='nexo-auditoria.csv';link.click();URL.revokeObjectURL(url);}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  auditEventLabel(event:string){return this.auditEvents.find(option=>option[0]===event)?.[1]||event;}
  auditMetadataFields(log:AuditLog){let metadata=log.metadata;if(typeof metadata==='string'){try{metadata=JSON.parse(metadata);}catch{return '';}}const fields=(metadata as Record<string,unknown>|null)?.['fields'];return Array.isArray(fields)?fields.join(', '):'';}
  auditMetadataRole(log:AuditLog){let metadata=log.metadata;if(typeof metadata==='string'){try{metadata=JSON.parse(metadata);}catch{return '';}}const role=(metadata as Record<string,unknown>|null)?.['role'];return typeof role==='string'?role:'';}
  async navigate(page:string){
    if(this.busy()||this.pageLoading()||this.uploadBusy()||this.attachmentBusy())return;
    this.mobileOpen.set(false);this.error.set('');this.notice.set('');this.view.set(page);this.pageLoading.set(true);window.scrollTo(0,0);
    try{if(page==='dashboard')await this.loadWorkspace();if(page==='board')await this.loadTasks();if(['notes','library','cheatsheets','diagrams'].includes(page)){this.librarySection=page==='cheatsheets'?'cheatsheets':'library';this.archived=false;await this.loadEntries(this.kindForView());}if(page==='history')await this.loadHistory();if(page==='audit'&&this.isAdmin()){await Promise.all([this.loadAuditLogs(),this.loadUsers()]);}if(page==='users')await this.loadUsers();if(page==='settings')await this.loadNotificationContacts();}finally{this.pageLoading.set(false);}
  }
  kindForView():'note'|'library'|'diagram'{return this.view()==='notes'?'note':this.view()==='diagrams'?'diagram':'library';}
  entryList(){return this.activeKind==='note'?'notes':this.activeKind==='diagram'?'diagrams':this.librarySection;}
  async toggleArchiveView(){if(this.pageLoading())return;this.archived=!this.archived;this.pageLoading.set(true);try{await this.loadEntries(this.kindForView());}finally{this.pageLoading.set(false);}}
  async filterEntries(){await this.loadEntries(this.kindForView());}
  async newEntry(kind:'note'|'library'|'diagram'){if(!this.canEdit()||this.busy()||this.pageLoading())return;this.entryDiagram=emptyDiagram();this.activeKind=kind;this.selectedEntry.set(null);this.entryDraft={title:'',color:kind==='note'?'yellow':'blue',pinned:false,archived:false,client_id:'',project_id:'',category:kind==='library'&&this.librarySection==='cheatsheets'?'Cheat sheets':'',tags:'',visibility:'private',shares:[]};this.entryBlocks=[{type:'text',text:''}];this.entryMedia.set([]);if(kind==='diagram')await this.loadMembers();this.view.set('entry');this.notice.set('');this.error.set('');this.mobileOpen.set(false);}
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
  async uploadFiles(files:File[]){if(this.attachmentBusy()||!files.length)return;if(files.length>8||files.some(f=>f.size>104857600)){this.error.set('Sube hasta 8 archivos de máximo 100 MB cada uno.');return;}if(this.librarySection==='cheatsheets'&&files.some(f=>this.fileType({name:f.name,mime:f.type,size:f.size,id:0} as Media)!=='image'&&this.fileType({name:f.name,mime:f.type,size:f.size,id:0} as Media)!=='pdf')){this.error.set('Los cheat sheets solo aceptan imágenes o PDF.');return;}this.attachmentBusy.set(true);this.error.set('');try{for(const file of files){const data=new FormData();data.append('file',file);const media=await firstValueFrom(this.http.post<Media>('/api/media',data));this.entryMedia.update(a=>[...a,media]);}}catch(e){this.showError(e);}finally{this.attachmentBusy.set(false);}}
  pickLibraryFiles(event:Event){const input=event.target as HTMLInputElement;this.uploadFiles(Array.from(input.files||[]));input.value='';}
  dropLibraryFiles(event:DragEvent){event.preventDefault();this.uploadFiles(Array.from(event.dataTransfer?.files||[]));}
  removeEntryFile(m:Media){if(!confirm(`¿Quitar ${m.name} de esta entrada? Al guardar, el archivo se eliminará permanentemente.`))return;this.entryMedia.update(a=>a.filter(f=>f.id!==m.id));this.entryBlocks=this.entryBlocks.filter(b=>b.media_id!==m.id);}
  editCatalog(kind:'clients'|'projects',item?:Catalog){this.catalogKind=kind;this.catalogId=item?.id||null;this.catalogDraft={code:item?.code||'',name:item?.name||'',description:item?.description||'',client_id:item?.client_id?String(item.client_id):''};this.catalogModal.set(true);}
  async saveCatalog(){if(this.busy())return;this.busy.set(true);this.error.set('');try{const payload={...this.catalogDraft,client_id:this.catalogDraft.client_id||null};await firstValueFrom(this.catalogId?this.http.put('/api/catalog/'+this.catalogKind+'/'+this.catalogId,payload):this.http.post('/api/catalog/'+this.catalogKind,payload));this.catalogModal.set(false);await this.loadCatalog();await this.loadTasks();this.notice.set('Guardado.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  async deleteCatalog(kind:'clients'|'projects',item:Catalog){if(this.busy()||!confirm('¿Eliminar '+item.name+' del catálogo? Las tareas y documentos se conservarán sin esta asociación.'))return;this.busy.set(true);try{await firstValueFrom(this.http.delete('/api/catalog/'+kind+'/'+item.id));await this.loadWorkspace();}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  drag(event:DragEvent,task:Task){event.dataTransfer?.setData('application/x-flujo-task',String(task.id));if(event.dataTransfer)event.dataTransfer.effectAllowed='move';}
  environmentName(code?:string|null){return code?this.environmentLabels[code]||code:'Ambiente anterior';}
  requiresEnvironmentReturnReason(task:Task|null,environment:string){const order=this.workflow().environments.map(item=>item.key);if(!task||task.environment===environment)return false;const from=order.indexOf(task.environment);const to=order.indexOf(environment);return from>=0&&to>=0&&from>to;}
  async dropTask(event:DragEvent,key:string){event.preventDefault();this.dragOver.set('');const id=Number(event.dataTransfer?.getData('application/x-flujo-task'));const task=this.tasks().find(t=>t.id===id);if(!task||this.busy())return;if(this.groupBy()==='status'){await this.move(task,key);return;}if(task.environment===key)return;this.moveEnvironment=key;this.moveStatus=this.defaultStatus(key);this.moveReason='';if(this.autoEnvironment()&&!this.requiresEnvironmentReturnReason(task,key))await this.performMove(task,key,this.defaultStatus(key));else this.moveTask.set(task);}
  showMove(task:Task){this.moveTask.set(task);this.moveEnvironment=task.environment;this.moveStatus=task.status;this.moveReason='';}
  async confirmMove(){const task=this.moveTask();if(task)await this.performMove(task,this.moveEnvironment,this.moveStatus,this.moveReason);}
  async performMove(task:Task,environment:string,status:string,rollbackReason=''){if(this.busy())return;if(!this.canUseStatus(environment,status,task.environment===environment?task.status:undefined)){this.error.set('Elige un estado activo disponible para el ambiente de destino. Puedes activarlo en Preferencias.');return;}this.busy.set(true);this.error.set('');try{const payload:{environment:string;status:string;rollback_reason?:string}={environment,status};if(this.requiresEnvironmentReturnReason(task,environment))payload.rollback_reason=rollbackReason.trim();const result=await firstValueFrom(this.http.patch<Task>('/api/tasks/'+task.id+'/move',payload));this.moveTask.set(null);await this.loadTasks();this.notice.set(result.notification==='sent'?'Tarea movida y correo enviado.':result.notification==='failed'?'Tarea movida, pero falló el envío del correo.':'Tarea movida a '+this.environmentLabels[environment]+'.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}

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
  editingUserPhotoUrl:string|null=null;userPhotoFile:File|null=null;userPhotoPreview=signal<string|null>(null);
  ownPhotoFile:File|null=null;ownPhotoPreview=signal<string|null>(null);savingOwnPhoto=signal(false);
  spaceModal=signal(false);editingSpace:number|null=null;spaceDraft={name:'',color:'blue'};
  get spaceName(){return this.spaces().find(s=>s.id===this.spaceId())?.name||'Mi espacio';}
  get activeSpaceAccent(){const color=this.spaces().find(s=>s.id===this.spaceId())?.color||'blue';const palette:Record<string,{light:string;dark:string}>={blue:{light:'#5266eb',dark:'#93a0ff'},green:{light:'#16794f',dark:'#6bd6a4'},yellow:{light:'#9a6600',dark:'#ffd164'},red:{light:'#c34350',dark:'#ff8b97'},purple:{light:'#7551c8',dark:'#b69bff'},gray:{light:'#566579',dark:'#b9c5d8'}};return palette[color]?.[this.theme()==='dark'?'dark':'light']||palette.blue[this.theme()==='dark'?'dark':'light'];}
  get activeEnvironmentKey(){if(this.view()==='detail')return this.selected()?.environment||this.draft.environment||'';if(this.view()==='taskreading')return this.selected()?.environment||'';if(this.view()==='board')return this.environment||'';return '';}
  get activeContextAccent(){const key=this.activeEnvironmentKey;if(!key)return this.activeSpaceAccent;const palette:Record<string,{light:string;dark:string}>={backlog:{light:'#7042b6',dark:'#d0b9ff'},local:{light:'#566579',dark:'#c2ccdb'},development:{light:'#3c68c5',dark:'#a9c0ff'},qa:{light:'#a66128',dark:'#f0bd8d'},certification:{light:'#98751f',dark:'#efd38a'},production:{light:'#187456',dark:'#9de0c2'}};return palette[key]?.[this.theme()==='dark'?'dark':'light']||this.activeSpaceAccent;}
  roleName(role:string){return role==='admin'?'Administrador':role==='reader'?'Solo lectura':'Editor';}
  setDefaultEdit(value:boolean){this.defaultEdit.set(value);localStorage.setItem('nexo-default-edit',value?'yes':'no');}
  asset(id:any,preview=false,attachment=false){return assetUrl('/api/'+(attachment?'attachments/':'media/')+id+'?workspace='+this.spaceId()+(preview?'&preview=1':''));}
  clearSpaceData(){this.generation++;this.tasks.set([]);this.counts.set({});this.clients.set([]);this.projects.set([]);this.notes.set([]);this.library.set([]);this.diagrams.set([]);this.notificationContacts.set([]);this.history.set([]);this.selected.set(null);this.selectedEntry.set(null);this.entryBlocks=[];this.taskBlocks=[];this.entryMedia.set([]);this.taskHistory.set([]);this.fullscreen.set(false);this.moveTask.set(null);this.catalogModal.set(false);this.query='';this.environment='';this.clientFilter='';this.projectFilter='';this.entryQuery='';this.entryClient='';this.entryProject='';this.entryCategory='';this.entryTag='';this.entryType='';this.archived=false;}
  async initializeSpaces(){const spaces=await firstValueFrom(this.http.get<Space[]>('/api/workspaces'));this.spaces.set(spaces);const saved=Number(localStorage.getItem('nexo-space'));const id=spaces.find(s=>s.id===saved)?.id||spaces[0]?.id;if(id){this.spaceId.set(id);localStorage.setItem('nexo-space',String(id));this.clearSpaceData();await this.loadWorkflow();if(this.user())this.loadStatusPreferences(this.user()!.id,id);this.view.set('dashboard');await this.loadWorkspace();}else this.error.set('Tu cuenta todavía no tiene un espacio asignado.');}
  async switchSpace(id:number){if(id===this.spaceId()||this.busy()||this.pageLoading()||this.uploadBusy()||this.attachmentBusy())return;this.clearSpaceData();this.spaceId.set(Number(id));localStorage.setItem('nexo-space',String(id));await this.loadWorkflow();if(this.user())this.loadStatusPreferences(this.user()!.id,id);this.view.set('dashboard');this.mobileOpen.set(false);this.error.set('');await this.loadWorkspace();this.notice.set('Ahora estás en '+this.spaceName+'.');}
  editSpace(s?:Space){this.editingSpace=s?.id||null;this.spaceDraft={name:s?.name||'',color:s?.color||'blue'};this.spaceModal.set(true);}
  async saveSpace(){if(this.busy())return;this.busy.set(true);try{const space=await firstValueFrom(this.editingSpace?this.http.put<Space>('/api/workspaces/'+this.editingSpace,this.spaceDraft):this.http.post<Space>('/api/workspaces',this.spaceDraft));this.spaces.set(await firstValueFrom(this.http.get<Space[]>('/api/workspaces')));this.spaceModal.set(false);this.notice.set('Espacio guardado: '+space.name+'.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  async loadUsers(){try{this.users.set(await firstValueFrom(this.http.get<User[]>('/api/users')));}catch(e){this.showError(e);}}
  profilePhotoUrl(person:User|null|undefined){return person?.profile_photo_url?assetUrl(person.profile_photo_url):null;}
  profilePhotoPreviewUrl(){return this.userPhotoPreview()||(this.editingUserPhotoUrl?assetUrl(this.editingUserPhotoUrl):null);}
  private photoFromEvent(event:Event):File|null{const input=event.target as HTMLInputElement,file=input.files?.[0]||null;input.value='';if(!file)return null;if(!['image/jpeg','image/png','image/webp'].includes(file.type)){this.error.set('Usa una imagen JPG, PNG o WEBP.');return null;}if(file.size>5*1024*1024){this.error.set('La foto debe pesar como máximo 5 MB.');return null;}this.error.set('');return file;}
  selectUserPhoto(event:Event){const file=this.photoFromEvent(event);if(!file)return;if(this.userPhotoPreview())URL.revokeObjectURL(this.userPhotoPreview()!);this.userPhotoFile=file;this.userPhotoPreview.set(URL.createObjectURL(file));}
  selectOwnPhoto(event:Event){const file=this.photoFromEvent(event);if(!file)return;if(this.ownPhotoPreview())URL.revokeObjectURL(this.ownPhotoPreview()!);this.ownPhotoFile=file;this.ownPhotoPreview.set(URL.createObjectURL(file));}
  private clearUserPhotoSelection(){if(this.userPhotoPreview())URL.revokeObjectURL(this.userPhotoPreview()!);this.userPhotoPreview.set(null);this.userPhotoFile=null;}
  private clearOwnPhotoSelection(){if(this.ownPhotoPreview())URL.revokeObjectURL(this.ownPhotoPreview()!);this.ownPhotoPreview.set(null);this.ownPhotoFile=null;}
  cancelOwnPhotoSelection(){this.clearOwnPhotoSelection();}
  closeUserModal(){this.userModal.set(false);this.userDraft.password='';this.editingUserPhotoUrl=null;this.clearUserPhotoSelection();}
  editUser(u?:User){this.clearUserPhotoSelection();this.editingUser=u?.id||null;this.editingUserPhotoUrl=u?.profile_photo_url||null;this.userDraft={name:u?.name||'',email:u?.email||'',password:'',role:u?.role||'editor',workspace_ids:u?.workspace_ids?[...u.workspace_ids]:[this.spaceId()]};this.userModal.set(true);}
  toggleUserSpace(id:number,checked:boolean){this.userDraft.workspace_ids=checked?[...this.userDraft.workspace_ids,id]:this.userDraft.workspace_ids.filter(x=>x!==id);}
  async saveUser(){if(this.busy())return;this.busy.set(true);this.error.set('');try{const payload={...this.userDraft,password:this.userDraft.password||null};const saved=await firstValueFrom(this.editingUser?this.http.put<User>('/api/users/'+this.editingUser,payload):this.http.post<User>('/api/users',payload));this.editingUser=saved.id;if(this.userPhotoFile){const data=new FormData();data.append('photo',this.userPhotoFile);const profile=await firstValueFrom(this.http.post<User>('/api/users/'+saved.id+'/photo',data));this.editingUserPhotoUrl=profile.profile_photo_url||null;}this.closeUserModal();await this.loadUsers();this.notice.set('Usuario guardado. Solo podrá acceder a los espacios que le asignaste.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  async removeEditedUserPhoto(){if(!this.editingUser||!this.editingUserPhotoUrl||this.busy()||!confirm('¿Quitar la foto de perfil de esta persona?'))return;this.busy.set(true);try{await firstValueFrom(this.http.delete('/api/users/'+this.editingUser+'/photo'));this.editingUserPhotoUrl=null;await this.loadUsers();this.notice.set('Foto de perfil quitada.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  async saveOwnProfilePhoto(){const person=this.user(),file=this.ownPhotoFile;if(!person||!file||this.busy())return;this.busy.set(true);this.savingOwnPhoto.set(true);try{const data=new FormData();data.append('photo',file);const updated=await firstValueFrom(this.http.post<User>('/api/users/'+person.id+'/photo',data));this.user.set(updated);this.clearOwnPhotoSelection();this.notice.set('Foto de perfil actualizada.');}catch(e){this.showError(e);}finally{this.savingOwnPhoto.set(false);this.busy.set(false);}}
  async removeOwnProfilePhoto(){const person=this.user();if(!person?.profile_photo_url||this.busy()||!confirm('¿Quitar tu foto de perfil?'))return;this.busy.set(true);try{await firstValueFrom(this.http.delete('/api/users/'+person.id+'/photo'));this.user.set({...person,profile_photo_url:null});this.notice.set('Foto de perfil quitada.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  editNotificationContact(c?:NotificationContact){this.editingContact=c?.id||null;this.contactDraft={name:c?.name||'',email:c?.email||'',channel:c?.channel||'email',is_default:c?.is_default??true};this.contactModal.set(true);}
  async saveNotificationContact(){if(this.busy())return;this.busy.set(true);try{const payload={...this.contactDraft};await firstValueFrom(this.editingContact?this.http.put('/api/notification-contacts/'+this.editingContact,payload):this.http.post('/api/notification-contacts',payload));this.contactModal.set(false);await this.loadNotificationContacts();this.notice.set('Destinatario guardado para este espacio.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  async deleteNotificationContact(c:NotificationContact){if(this.busy()||!confirm(`¿Quitar a ${c.name} de los destinatarios guardados?`))return;this.busy.set(true);try{await firstValueFrom(this.http.delete('/api/notification-contacts/'+c.id));await this.loadNotificationContacts();this.notice.set('Destinatario quitado.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  readEntry(){const e=this.selectedEntry();if(e){this.openEntry(e);this.view.set('reading');}}
  async saveNoteChecklist(blocks:Block[]){if(this.busy()||this.uploadBusy()||this.attachmentBusy()||this.activeKind!=='note'||!this.canEditEntry())return;const previous=this.selectedEntry(),previousBlocks=structuredClone(this.entryBlocks);this.entryBlocks=structuredClone(blocks);await this.saveEntry();if(this.error()&&previous){this.entryBlocks=previousBlocks;this.selectedEntry.set({...previous,blocks:structuredClone(previous.blocks)});}}

  private currentAutosaveSnapshot(){return JSON.stringify(this.view()==='detail'?{draft:this.draft,description:this.taskDescriptionBlocks,blocks:this.taskBlocks}:this.view()==='entry'?{draft:this.entryDraft,blocks:this.entryBlocks,diagram:this.entryDiagram,media:this.entryMedia().map(m=>m.id)}:{});}
  private async autosaveTick(){if(this.busy()||this.uploadBusy()||this.attachmentBusy()||!['detail','entry'].includes(this.view()))return;if(this.view()==='detail'&&!this.selected())return;if(this.view()==='entry'&&!this.selectedEntry())return;const title=this.view()==='detail'?this.draft.title:this.entryDraft.title;if(!title.trim())return;const snapshot=this.currentAutosaveSnapshot();if(!this.autosaveSnapshot){this.autosaveSnapshot=snapshot;return;}if(snapshot===this.autosaveSnapshot)return;this.view()==='detail'?await this.save(true):await this.saveEntry(true);}

  async ngOnInit() {
    this.initializeTheme();
    try {
      const result = await firstValueFrom(this.http.get<{user: User | null}>(isNativeMobile ? '/api/mobile/session' : '/api/session'));
      this.user.set(result.user);
      if (result.user) this.loadAppearancePreferences(result.user.id);
      if (result.user) {
        this.loadSidebarPreferences(result.user.id);this.loadDashboardStatPreferences(result.user.id);
        if (isNativeMobile) await this.refreshMobileAssetToken();
        await this.initializeSpaces();
      }
      this.autosaveTimer=setInterval(()=>this.autosaveTick(),1800);
    } catch (e) {
      if (isNativeMobile && !localStorage.getItem('nexo-mobile-token')) {
        this.user.set(null);
        this.error.set('');
      } else {
        this.showError(e);
      }
    } finally { this.ready.set(true); }
  }
  private showError(e: unknown) {
    let message = 'No se pudo completar la operación. Inténtalo nuevamente.';
    if (e instanceof HttpErrorResponse) {
      if (e.status === 401 || e.status === 419) {
        if (isNativeMobile) {
          localStorage.removeItem('nexo-mobile-token');
          localStorage.removeItem('nexo-mobile-asset-token');
        }
        this.user.set(null); this.clearSpaceData(); this.view.set('board'); this.tasks.set([]); this.selected.set(null); this.password = '';
        message = 'Tu sesión terminó. Vuelve a iniciar sesión.';
        firstValueFrom(this.http.get('/api/session')).catch(() => {});
      } else if (e.status === 422) {
        message = Object.values(e.error.errors || {}).flat().join(' ') || 'Revisa los campos del formulario.';
      } else if (e.status === 429) message = e.error?.blocked && e.error?.message ? e.error.message : 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.';
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
      const challenge=this.loginCaptcha();
      const credentials={email:this.email,password:this.password,...(challenge?{captcha_id:challenge.id,captcha_answer:this.loginCaptchaAnswer}:{}),...(this.twoFactorRequired()?{two_factor_code:this.twoFactorCode}:{})};
      const result = isNativeMobile
        ? await firstValueFrom(this.http.post<{user: User; token: string}>('/api/mobile/login', credentials))
        : await firstValueFrom(this.http.post<{user: User}>('/api/login', credentials));
      const mobileToken = (result as {token?: string}).token;
      if (isNativeMobile && mobileToken) localStorage.setItem('nexo-mobile-token', mobileToken);
      if (isNativeMobile) await this.refreshMobileAssetToken();
      this.user.set(result.user); this.loadAppearancePreferences(result.user.id);this.loadSidebarPreferences(result.user.id);this.loadDashboardStatPreferences(result.user.id); this.password = ''; this.loginCaptcha.set(null); this.loginCaptchaAnswer='';this.twoFactorRequired.set(false);this.twoFactorCode=''; this.notice.set(''); await this.initializeSpaces();
    } catch (e) {
      if(e instanceof HttpErrorResponse){
        const response=e.error as {captcha_required?:boolean;captcha_id?:string;captcha_question?:string;two_factor_required?:boolean;blocked?:boolean}|null;
        if(response?.two_factor_required)this.twoFactorRequired.set(true);
        if(response?.captcha_required&&response.captcha_id&&response.captcha_question){this.loginCaptcha.set({id:response.captcha_id,question:response.captcha_question});this.loginCaptchaAnswer='';}
        else if(response?.blocked)this.loginCaptcha.set(null);
      }
      this.showError(e);
    } finally { this.busy.set(false); }
  }
  resetTwoFactorLogin(){this.twoFactorRequired.set(false);this.twoFactorCode='';}
  async logout() {
    if (this.busy()) return;
    this.busy.set(true);
    try { await firstValueFrom(this.http.post('/api/logout', {})); localStorage.removeItem('nexo-mobile-token'); localStorage.removeItem('nexo-mobile-asset-token'); this.user.set(null); this.clearSpaceData(); this.tasks.set([]); this.selected.set(null); this.draft = this.emptyDraft(); this.view.set('board'); this.error.set(''); this.notice.set(''); }
    catch (e) { this.showError(e); } finally { this.busy.set(false); }
  }
  twoFactorSetup=signal<{secret:string;otpauth_uri:string}|null>(null);twoFactorRecoveryCodes=signal<string[]|null>(null);twoFactorSetupPassword='';twoFactorSetupCode='';twoFactorDisableOpen=signal(false);twoFactorDisablePassword='';twoFactorDisableCode='';
  currentPassword='';newPassword='';confirmNewPassword='';
  async changeOwnPassword(){if(this.busy())return;if(this.newPassword!==this.confirmNewPassword){this.error.set('La nueva contraseña y su confirmación no coinciden.');return;}this.busy.set(true);this.error.set('');try{await firstValueFrom(this.http.put('/api/account/password',{current_password:this.currentPassword,password:this.newPassword,password_confirmation:this.confirmNewPassword}));this.currentPassword='';this.newPassword='';this.confirmNewPassword='';this.notice.set('Contraseña actualizada. Se cerraron tus otras sesiones.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  async beginTwoFactorSetup(){if(this.busy())return;this.busy.set(true);this.error.set('');try{const setup=await firstValueFrom(this.http.post<{secret:string;otpauth_uri:string}>('/api/two-factor/setup',{password:this.twoFactorSetupPassword}));this.twoFactorSetup.set(setup);this.twoFactorSetupPassword='';this.twoFactorSetupCode='';}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  async confirmTwoFactorSetup(){if(this.busy()||!this.twoFactorSetupCode.trim())return;this.busy.set(true);this.error.set('');try{const result=await firstValueFrom(this.http.post<{enabled:boolean;recovery_codes:string[]}>('/api/two-factor/confirm',{code:this.twoFactorSetupCode}));const person=this.user();if(person)this.user.set({...person,two_factor_enabled:result.enabled});this.twoFactorSetup.set(null);this.twoFactorSetupCode='';this.twoFactorRecoveryCodes.set(result.recovery_codes);this.notice.set('Verificación en dos pasos activada. Guarda tus códigos de recuperación.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  async disableTwoFactor(){if(this.busy()||!this.twoFactorDisablePassword||!this.twoFactorDisableCode.trim())return;this.busy.set(true);this.error.set('');try{await firstValueFrom(this.http.post('/api/two-factor/disable',{password:this.twoFactorDisablePassword,code:this.twoFactorDisableCode}));const person=this.user();if(person)this.user.set({...person,two_factor_enabled:false});this.twoFactorDisablePassword='';this.twoFactorDisableCode='';this.twoFactorDisableOpen.set(false);this.notice.set('Verificación en dos pasos desactivada.');}catch(e){this.showError(e);}finally{this.busy.set(false);}}
  private async refreshMobileAssetToken() {
    if (!isNativeMobile) return;
    const result = await firstValueFrom(this.http.get<{token:string}>('/api/mobile/asset-token'));
    localStorage.setItem('nexo-mobile-asset-token', result.token);
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
  newTask(status?:string) {
    if(!this.canEdit()||this.pageLoading())return;
    let targetStatus=status||'pending';if(this.groupBy()==='status'&&!status)targetStatus=this.statuses.find(([key])=>this.statusSetting(key).active&&this.statusSetting(key).visible)?.[0]||this.statuses.find(([key])=>this.statusSetting(key).active)?.[0]||targetStatus;if(this.groupBy()==='status'&&(!this.statusSetting(targetStatus).active||!this.statusSetting(targetStatus).visible)){this.error.set('Activa y muestra ese estado en Preferencias para crear tareas.');return;}
    const environment=this.groupBy()==='environment'&&this.workflowEnvironment(targetStatus)?.active?targetStatus:this.groupBy()==='status'?(this.workflow().environments.find(item=>item.active&&item.allowed_statuses.includes(targetStatus))?.key||this.workflow().environments.find(item=>item.active)?.key||'backlog'):(this.environment||this.workflow().environments.find(item=>item.active)?.key||'backlog');
    this.selected.set(null); this.draft = {...this.emptyDraft(),notify_emails:this.defaultRecipients('email'),notify_message_emails:this.defaultRecipients('message'),task_type:'task', status: this.groupBy()==='status'?targetStatus:'pending', environment};this.taskDescriptionBlocks=[{type:'text',text:''}]; this.taskBlocks=[{type:'text',text:''}]; this.draft.status=this.defaultStatus(this.draft.environment,this.draft.status); this.files = []; this.view.set('detail'); this.error.set(''); this.notice.set(''); window.scrollTo(0, 0);
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
      this.draft = {task_type:full.task_type||'task',tags:(full.tags||[]).join(', '),is_fire:!!full.is_fire,notify_on_production:!!full.notify_on_production,notify_emails:(full.notify_emails||[]).join(', '),email_notification_events:full.email_notification_events||['production_completed'],notification_message:full.notification_message||'¡Listo! 🚀 La tarea ya está en Producción.',notify_message_on_production:!!full.notify_message_on_production,notify_message_emails:(full.notify_message_emails||[]).join(', '),notification_message_short:full.notification_message_short||'',notify_include_client:fields.includes('client'),notify_include_project:fields.includes('project'),notify_include_title:fields.includes('title'),notify_include_description:fields.includes('description'),notify_include_code:fields.includes('code'),notify_include_status:fields.includes('status'),notify_include_checklist:fields.includes('checklist'),notify_include_attachments:fields.includes('attachments'),environment_return_resolved:!!full.environment_return_resolved,environment_return_solution:full.environment_return_solution||'',estimated_delivery_at:full.estimated_delivery_at?String(full.estimated_delivery_at).slice(0,16):'',client_id:full.client_id?String(full.client_id):'', project_id:full.project_id?String(full.project_id):'', sql_notes:full.sql_notes||'', title: full.title, description: full.description || '', status: full.status, environment: full.environment, priority: full.priority, checklist_text: (full.checklist || []).map(s => s.text).join('\n')};
      this.files = []; this.view.set(this.defaultEdit()&&this.canEdit()?'detail':'taskreading'); this.loadHistory(task.id); window.scrollTo(0, 0);
    } catch (e) { this.showError(e); } finally { this.busy.set(false);this.pageLoading.set(false); }
  }
  private improveWriting(text:string, description=false){
    let value=text.replace(/\s+/g,' ').replace(/\s+([,.!?;:])/g,'$1').trim();
    const replacements:Array<[string,string]>=[['\\bq\\b','que'],['\\bxq\\b','porque'],['\\btmb\\b','también'],['\\bdnd\\b','donde'],['\\bqe\\b','que'],['\\bproblma\\b','problema'],['\\bconfiguracion\\b','configuración'],['\\bfuncion\\b','función']];
    for(const [pattern,replacement] of replacements)value=value.replace(new RegExp(pattern,'gi'),replacement);
    value=value.replace(/(^|[.!?]\s+)([a-záéíóúñ])/g,(_,prefix,letter)=>prefix+letter.toUpperCase());
    if(value&&!/[.!?]$/.test(value)&&description)value+='.';
    return value;
  }
  openWritingImprovement(){
    if(!this.canEdit()||this.view()!=='detail')return;
    const originalTitle=this.draft.title,originalDescription=this.taskDescriptionBlocks.map(block=>block.type==='text'?block.text:'').filter(Boolean).join('\n\n')||this.draft.description||'';
    this.writingImprove.set({originalTitle,originalDescription,title:this.improveWriting(originalTitle),description:this.improveWriting(originalDescription,true)});
  }
  applyWritingImprovement(){
    const suggestion=this.writingImprove();if(!suggestion)return;
    this.draft.title=suggestion.title;this.draft.description=suggestion.description;this.taskDescriptionBlocks=[{type:'text',text:suggestion.description}];this.writingImprove.set(null);this.notice.set('Mejora aplicada. Revisa la tarea antes de guardarla.');
  }
  openStatusModal(){
    const all=this.tasks(),pending=all.filter(task=>!(task.environment==='production'&&task.status==='done')),completed=all.filter(task=>task.environment==='production'&&task.status==='done');
    const line=(task:Task)=>`- ${this.taskCode(task.id)} · ${task.title} — ${this.environmentLabels[task.environment]||task.environment} / ${this.statusLabels[task.status]||task.status}`;
    const body=[`Hola,`,``,`Estatus general del trabajo`,`Total de tareas: ${all.length}`,`Producción completada: ${completed.length}`,`Pendientes o fuera de Producción completada: ${pending.length}`,``,`Pendientes y tareas por completar:`,pending.length?pending.map(line).join('\n'):'No hay tareas pendientes.',``,`Tareas completadas en Producción:`,completed.length?completed.map(line).join('\n'):'No hay tareas completadas en Producción.'].join('\n');
    this.statusDraft.set({subject:'Estatus general de Nexo',body});
  }
  async copyStatus(){const status=this.statusDraft();if(!status)return;try{await navigator.clipboard.writeText(`${status.subject}\n\n${status.body}`);this.notice.set('Estatus copiado.');}catch{this.error.set('No se pudo copiar el estatus.');}}
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
    const current=this.selected(),currentStatus=current?.environment===this.draft.environment?current.status:undefined;if(!this.canUseStatus(this.draft.environment,this.draft.status,currentStatus)){this.error.set('No hay un estado activo válido para este ambiente. Activa uno en Preferencias o elige otro estado.');if(auto)this.autosaveState.set('idle');return;}
    this.busy.set(true); if(auto)this.autosaveState.set('saving');this.error.set(''); if(!auto)this.notice.set('');
    const data = new FormData();
    Object.entries(this.draft).forEach(([key, value]) => {if(key!=='email_notification_events')data.append(key, typeof value==='boolean'?(value?'1':'0'):String(value));});
    data.append('email_notification_events_present','1');
    this.draft.email_notification_events.forEach(event=>data.append('email_notification_events[]',event));
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
    try { if(!this.canUseStatus(task.environment,status,task.status)){this.error.set('Ese estado no está activo o no está disponible en '+this.environmentLabels[task.environment]+'. Activa otro estado en Preferencias o cambia el ambiente.');return;} const result=await firstValueFrom(this.http.patch<Task>(`/api/tasks/${task.id}/status`, {status})); await this.loadTasks(); this.notice.set(result.notification==='sent'?'Estado actualizado y correo enviado.':result.notification==='failed'?'Estado actualizado, pero falló el envío del correo.':'Estado actualizado.'); }
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
