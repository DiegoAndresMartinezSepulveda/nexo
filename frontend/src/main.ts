import { bootstrapApplication } from '@angular/platform-browser';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { AppComponent } from './app';
import { apiUrl, isNativeMobile } from './mobile';

bootstrapApplication(AppComponent, {
  providers: [provideHttpClient(withInterceptors([(req, next) => {
    const token = isNativeMobile ? localStorage.getItem('nexo-mobile-token') : null;
    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...(localStorage.getItem('nexo-space') ? {'X-Workspace-ID': localStorage.getItem('nexo-space')!} : {}),
      ...(token ? {Authorization: `Bearer ${token}`} : {}),
    };
    return next(req.clone({url: apiUrl(req.url), setHeaders: headers}));
  }]))]
}).catch(console.error);
