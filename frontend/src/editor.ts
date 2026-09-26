import {Component, Input, Output, EventEmitter, ViewChild, ElementRef, AfterViewInit, inject, signal, Pipe, PipeTransform} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FormsModule} from '@angular/forms';
import {HttpClient} from '@angular/common/http';
import {DomSanitizer} from '@angular/platform-browser';
import {firstValueFrom} from 'rxjs';
import DOMPurify from 'dompurify';
import {assetUrl} from './mobile';

export type Block = {type:'text'|'image'; text?:string; html?:string; media_id?:number; caption?:string};
export type Media = {id:number; name:string; mime:string; size:number};
const tags=['p','div','span','b','strong','i','em','u','s','strike','br','h2','h3','ul','ol','li','blockquote','pre','code','font'];
DOMPurify.addHook('uponSanitizeAttribute',(_node,data)=>{
  if(data.attrName!=='style')return;
  const style=document.createElement('span').style; style.cssText=data.attrValue;
  const allowed=['color','background-color','font-size','font-family','text-align','font-weight','font-style','text-decoration'];
  data.attrValue=allowed.map(p=>{const v=style.getPropertyValue(p);return v&&!/url|expression|var\(|@|\\/i.test(v)?`${p}:${v}`:'';}).filter(Boolean).join(';');
});
export function cleanHTML(html:string) { return DOMPurify.sanitize(html,{ALLOWED_TAGS:tags,ALLOWED_ATTR:['style','color','size','face','class'],ALLOW_DATA_ATTR:false,ALLOW_ARIA_ATTR:false}); }
@Pipe({name:'safeNote',standalone:true})
export class SafeNotePipe implements PipeTransform {
  private sanitizer=inject(DomSanitizer);
  transform(html:string) { return this.sanitizer.bypassSecurityTrustHtml(cleanHTML(html)); }
}
@Component({selector:'app-modal',standalone:true,template:`<dialog #dialog [class.fullscreen-dialog]="fullscreen" (cancel)="cancel($event)" (click)="outside($event)"><div class="modal-heading"><h2>{{title}}</h2><button type="button" class="icon-button" (click)="closed.emit()" aria-label="Cerrar ventana">×</button></div><ng-content></ng-content></dialog>`})
export class ModalComponent implements AfterViewInit {
  @Input() fullscreen=false; @Input() title=''; @Output() closed=new EventEmitter<void>(); @ViewChild('dialog') dialog!:ElementRef<HTMLDialogElement>;
  ngAfterViewInit(){this.dialog.nativeElement.showModal();}
  cancel(event:Event){event.preventDefault();this.closed.emit();}
  outside(event:MouseEvent){if(event.target===this.dialog.nativeElement){const r=this.dialog.nativeElement.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)this.closed.emit();}}
}
@Component({selector:'app-rich-text',standalone:true,imports:[CommonModule],template:`
  <div class="rich-toolbar" role="toolbar" aria-label="Formato de texto" (mousedown)="remember()">
    <select aria-label="Fuente" (change)="command('fontName',$any($event.target).value)"><option value="Arial">Sans serif</option><option value="Georgia">Serif</option><option value="Courier New">Monoespaciada</option></select>
    <select aria-label="Tamaño de letra" (change)="command('fontSize',$any($event.target).value)"><option value="3">Normal</option><option value="2">Pequeña</option><option value="4">Grande</option><option value="5">Muy grande</option></select>
    <button type="button" title="Negrita (Ctrl+B)" aria-label="Negrita" (mousedown)="$event.preventDefault()" (click)="command('bold')"><b>B</b></button>
    <button type="button" title="Cursiva" aria-label="Cursiva" (mousedown)="$event.preventDefault()" (click)="command('italic')"><i>I</i></button>
    <button type="button" title="Subrayado" aria-label="Subrayado" (mousedown)="$event.preventDefault()" (click)="command('underline')"><u>U</u></button>
    <button type="button" title="Título" aria-label="Convertir en título" (mousedown)="$event.preventDefault()" (click)="command('formatBlock','h2')">H₂</button>
    <button type="button" title="Lista" aria-label="Lista con viñetas" (mousedown)="$event.preventDefault()" (click)="command('insertUnorderedList')">• ≡</button>
    <button type="button" title="Lista numerada" aria-label="Lista numerada" (mousedown)="$event.preventDefault()" (click)="command('insertOrderedList')">1. ≡</button>
    <button type="button" title="Lista de tareas" aria-label="Lista con casillas" (mousedown)="$event.preventDefault()" (click)="checklist()">☑ ≡</button>
    <label class="text-color" title="Color del texto">A<input type="color" aria-label="Color del texto" value="#4169e1" (input)="command('foreColor',$any($event.target).value)"></label>
    <button type="button" title="Quitar formato" aria-label="Quitar formato" (mousedown)="$event.preventDefault()" (click)="command('removeFormat')">T×</button>
  </div>
  <div #area contenteditable="true" role="textbox" aria-multiline="true" [attr.aria-label]="label" class="rich-area" data-placeholder="Escribe una nota… puedes pegar una captura con Ctrl+V" (input)="changed()" (change)="changed()" (keyup)="remember()" (mouseup)="remember()" (paste)="paste($event)"></div>
`})
export class RichTextComponent implements AfterViewInit {
  @Input() html=''; @Input() text=''; @Input() label='Texto de la nota';
  @Output() edited=new EventEmitter<{html:string;text:string}>(); @Output() images=new EventEmitter<File[]>();
  @ViewChild('area') area!:ElementRef<HTMLDivElement>; private range:Range|null=null;
  ngAfterViewInit(){const el=this.area.nativeElement;if(this.html)el.innerHTML=cleanHTML(this.html);else el.innerText=this.text;}
  remember(){const s=window.getSelection();if(s?.rangeCount&&this.area.nativeElement.contains(s.anchorNode))this.range=s.getRangeAt(0).cloneRange();}
  command(name:string,value?:string){const el=this.area.nativeElement;el.focus();const s=window.getSelection();if(this.range&&el.contains(this.range.commonAncestorContainer)){s?.removeAllRanges();s?.addRange(this.range);}document.execCommand('styleWithCSS',false,'false');document.execCommand(name,false,value);this.remember();this.changed();}
  checklist(){const el=this.area.nativeElement;el.focus();document.execCommand('insertHTML',false,'<ul class="rich-checklist"><li>☐ Nueva tarea</li></ul><p><br></p>');this.changed();}
  changed(){this.edited.emit({html:cleanHTML(this.area.nativeElement.innerHTML),text:this.area.nativeElement.innerText});}
  paste(event:ClipboardEvent){
    event.preventDefault(); const files=Array.from(event.clipboardData?.files||[]).filter(f=>f.type.startsWith('image/'));
    if(files.length){this.images.emit(files);return;}
    const html=event.clipboardData?.getData('text/html');
    if(html)document.execCommand('insertHTML',false,cleanHTML(html));else document.execCommand('insertText',false,event.clipboardData?.getData('text/plain')||'');
    this.changed();
  }
}
@Component({selector:'app-note-editor',standalone:true,imports:[CommonModule,FormsModule,RichTextComponent,ModalComponent],template:`
  <div class="note-editor" (dragover)="$event.preventDefault()" (drop)="drop($event)" [class.uploading]="uploading()">
    @for(block of blocks;track block;let i=$index){
      <div class="editor-block">
      @if(block.type==='text'){
        <app-rich-text [html]="block.html||''" [text]="block.text||''" (edited)="edit(block,$event)" (images)="upload($event,i)"></app-rich-text>
      }@else{
        <figure><button type="button" class="image-open" (click)="preview.set(block.media_id!)" aria-label="Ampliar captura"><img [src]="asset(block.media_id)" [alt]="block.caption||'Captura adjunta'"></button><input [ngModel]="block.caption" (ngModelChange)="block.caption=$event;publish()" [ngModelOptions]="{standalone:true}" placeholder="Añadir una descripción a la imagen" aria-label="Descripción de la imagen"></figure>
      }
      @if(blocks.length>1){<button class="remove-block" type="button" (click)="remove(i)" [disabled]="uploading()" [attr.aria-label]="block.type==='image'?'Quitar imagen':'Quitar bloque de texto'">×</button>}
      </div>
    }
    <div class="editor-footer"><button type="button" class="text-button" (click)="addText()">＋ Texto</button><label class="upload-label">＋ Imagen<input type="file" accept="image/png,image/jpeg,image/webp,image/gif" multiple (change)="pick($event)"></label><span>{{uploading()?'Subiendo capturas…':'Ctrl+V o arrastra imágenes aquí · 10 MB por imagen'}}</span></div>
  </div>
  @if(preview()){<app-modal title="Captura" (closed)="preview.set(null)"><img class="lightbox-image" [src]="asset(preview())" alt="Captura ampliada"></app-modal>}
`})
export class NoteEditorComponent {
  asset(id:any){return assetUrl('/api/media/'+id+'?preview=1&workspace='+localStorage.getItem('nexo-space'));}
  private http=inject(HttpClient); @Input() blocks:Block[]=[]; @Output() blocksChange=new EventEmitter<Block[]>(); @Output() uploadState=new EventEmitter<boolean>(); @Output() failure=new EventEmitter<string>();
  uploading=signal(false); preview=signal<number|null>(null);
  publish(){this.blocksChange.emit([...this.blocks]);}
  edit(block:Block,value:{html:string;text:string}){Object.assign(block,value);this.publish();}
  addText(){this.blocks=[...this.blocks,{type:'text',text:''}];this.publish();}
  remove(i:number){this.blocks=this.blocks.filter((_,n)=>i!==n);this.publish();}
  pick(event:Event){const input=event.target as HTMLInputElement;this.upload(Array.from(input.files||[]));input.value='';}
  drop(event:DragEvent){event.preventDefault();this.upload(Array.from(event.dataTransfer?.files||[]));}
  async upload(files:File[],after?:number){
    if(this.uploading()||!files.length)return;
    if(files.length>8||files.some(f=>!['image/png','image/jpeg','image/gif','image/webp'].includes(f.type)||f.size>10485760)){this.failure.emit('Sube hasta 8 imágenes PNG, JPG, GIF o WebP de máximo 10 MB.');return;}
    this.uploading.set(true);this.uploadState.emit(true);
    let index=after===undefined?this.blocks.length:after+1;
    try {for(const file of files){const data=new FormData();data.append('file',file);const asset=await firstValueFrom(this.http.post<Media>('/api/media',data));this.blocks.splice(index++,0,{type:'image',media_id:asset.id,caption:''});this.publish();}this.blocks.splice(index,0,{type:'text',text:''});this.publish();}
    catch{this.failure.emit('No se pudo subir la imagen. Revisa tu conexión o vuelve a iniciar sesión. Las imágenes ya subidas siguen en la nota.');}
    finally{this.uploading.set(false);this.uploadState.emit(false);}
  }
}
