import { Capacitor } from '@capacitor/core';

/** The Laravel host used by the Android build. Keep this free of credentials. */
export const MOBILE_API_ORIGIN = 'https://diegomartinezsepulveda.cl';
export const isNativeMobile = Capacitor.isNativePlatform();

export function apiUrl(url: string): string {
  return isNativeMobile && url.startsWith('/api/') ? MOBILE_API_ORIGIN + url : url;
}

export function assetUrl(url: string): string {
  const isUserPhoto = /^\/api\/users\/\d+\/photo(?:\?|$)/.test(url);
  if (!isNativeMobile || (!url.startsWith('/api/media/') && !url.startsWith('/api/attachments/') && !isUserPhoto)) return url;

  const token = localStorage.getItem('nexo-mobile-asset-token');
  const separator = url.includes('?') ? '&' : '?';
  return MOBILE_API_ORIGIN + url + (token ? `${separator}asset_token=${encodeURIComponent(token)}` : '');
}
