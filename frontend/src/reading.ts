import {Component,EventEmitter,Input,Output} from '@angular/core';
import {CommonModule} from '@angular/common';
import {Block,Media,SafeNotePipe,ModalComponent} from './editor';
import {assetUrl} from './mobile';
@Component({selector:'app-reading',standalone:true,imports:[CommonModule,SafeNotePipe,ModalComponent],template:`
<div class="reading-content" (click)="toggleChecklistItem($event)">@for(block of blocks;track $index){@if(block.type==='text'){<div class="reading-text" [innerHTML]="(block.html||plain(block.text||''))|safeNote"></div>}@else{<figure><button type="button" class="reading-image" (click)="image=block.media_id||null" aria-label="Ampliar imagen"><img [src]="asset(block.media_id,true)" [alt]="block.caption||'Captura de referencia'" loading="lazy"></button>@if(block.caption){<figcaption>{{block.caption}}</figcaption>}</figure>}}@empty{<p class="muted">Sin contenido adicional.</p>}</div>
@if(files.length){<h3>Archivos</h3><div class="reading-files">@for(file of files;track file.id){<div class="attachment"><div><strong>{{file.name}}</strong><small>{{file.size/1024|number:'1.1-1'}} KB</small></div><a class="button secondary" [href]="asset(file.id)">Descargar</a>@if(file.mime.startsWith('image/')||file.mime==='application/pdf'){<a class="text-button" [href]="asset(file.id,true)" target="_blank" rel="noopener">Ver</a>}</div>}</div>}
@if(image){<app-modal title="Captura" [fullscreen]="true" (closed)="image=null"><img class="reading-zoom" [src]="asset(image,true)" alt="Captura ampliada"></app-modal>}
`})
export class ReadingComponent {
 @Input() blocks:Block[]=[];@Input() files:Media[]=[];@Input() checklistEditable=false;@Output() blocksChange=new EventEmitter<Block[]>();image:number|null=null;
 toggleChecklistItem(event:MouseEvent){if(!this.checklistEditable)return;const box=(event.target as HTMLElement).closest('.note-task-box'),root=event.currentTarget as HTMLElement,content=box?.closest('.reading-text');if(!box||!content||!root.contains(box))return;event.preventDefault();const checked=box.textContent?.trim()==='☑';box.textContent=checked?'☐':'☑';box.classList.toggle('checked',!checked);box.parentElement?.querySelector('.note-task-label')?.classList.toggle('checked',!checked);const textBlocks=this.blocks.filter(block=>block.type==='text');const index=Array.from(root.querySelectorAll('.reading-text')).indexOf(content as HTMLElement);if(index<0||!textBlocks[index])return;textBlocks[index]={...textBlocks[index],html:content.innerHTML,text:content.textContent||''};this.blocksChange.emit([...this.blocks]);}
 asset(id:any,preview=false){return assetUrl('/api/media/'+id+'?workspace='+localStorage.getItem('nexo-space')+(preview?'&preview=1':''));}
 plain(text:string){return text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');}
}
