import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import './index.css';
import './lib/i18n';

// ثبت Service Worker + Push
async function bootstrap() {
  if ('serviceWorker' in navigator) {
    try {
      const { registerSW } = await import('virtual:pwa-register');
      const updateSW = registerSW({
        onNeedRefresh() {
          // می‌توان یک toast نمایش داد
          // eslint-disable-next-line no-console
          console.info('[pwa] update available');
        },
        onOfflineReady() {
          // eslint-disable-next-line no-console
          console.info('[pwa] offline ready');
          // ثبت رویداد client
          if (window.parsyarPortal?.logEvent) {
            window.parsyarPortal.logEvent('pwa_offline_ready', {});
          }
        }
      });
      // eslint-disable-next-line @typescript-eslint/no-unused-vars
      const _u = updateSW;
    } catch (e) {
      // eslint-disable-next-line no-console
      console.warn('[pwa] register failed', e);
    }
  }
}
bootstrap();

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </React.StrictMode>
);
