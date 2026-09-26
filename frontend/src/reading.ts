import {Component,Input} from '@angular/core';
import {CommonModule} from '@angular/common';
import {Block,Media,SafeNotePipe,ModalComponent} from './editor';
import {assetUrl} from './mobile';
@Component({selector:'app-reading',standalone:true,imports:[CommonModule,SafeNotePipe,ModalComponent],template:`
<div class="reading-content">@for(block of blocks;track $index){@if(block.type==='text'){<div class="reading-text" [innerHTML]="(block.html||plain(block.text||''))|safeNote"></div>}@else{<figure><button type="button" class="reading-image" (click)="image=block.media_id||null" aria-label="Ampliar imagen"><img [src]="asset(block.media_id,true)" [alt]="block.caption||'Captura de referencia'" loading="lazy"></button>@if(block.caption){<figcaption>{{block.caption}}</figcaption>}</figure>}}@empty{<p class="muted">Sin contenido adicional.</p>}</div>
@if(files.length){<h3>Archivos</h3><div class="reading-files">@for(file of files;track file.id){<div class="attachment"><div><strong>{{file.name}}</strong><small>{{file.size/1024|number:'1.1-1'}} KB</small></div><a class="button secondary" [href]="asset(file.id)">Descargar</a>@if(file.mime.startsWith('image/')||file.mime==='application/pdf'){<a class="text-button" [href]="asset(file.id,true)" target="_blank" rel="noopener">Ver</a>}</div>}</div>}
@if(image){<app-modal title="Captura" [fullscreen]="true" (closed)="image=null"><img class="reading-zoom" [src]="asset(image,true)" alt="Captura ampliada"></app-modal>}
`})
export class ReadingComponent {
 @Input() blocks:Block[]=[];@Input() files:Media[]=[];image:number|null=null;
 asset(id:any,preview=false){return assetUrl('/api/media/'+id+'?workspace='+localStorage.getItem('nexo-space')+(preview?'&preview=1':''));}
 plain(text:string){return text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');}
}
