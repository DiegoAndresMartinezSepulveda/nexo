import {Directive, ElementRef, HostBinding, inject} from '@angular/core';

@Directive({selector: 'button[aria-label], a[aria-label]', standalone: true})
export class ButtonHintDirective {
  private element = inject(ElementRef<HTMLButtonElement | HTMLAnchorElement>);
  private originalTitle = this.element.nativeElement.getAttribute('title');

  @HostBinding('attr.title')
  get hint(): string {
    return this.originalTitle || this.element.nativeElement.getAttribute('aria-label') || '';
  }
}
