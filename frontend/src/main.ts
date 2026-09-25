import { bootstrapApplication } from '@angular/platform-browser';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { AppComponent } from './app';

bootstrapApplication(AppComponent, {
  providers: [provideHttpClient(withInterceptors([(req, next) => next(req.clone({setHeaders: {Accept: 'application/json', ...(localStorage.getItem('nexo-space')?{'X-Workspace-ID':localStorage.getItem('nexo-space')!}:{})}}))]))]
}).catch(console.error);
