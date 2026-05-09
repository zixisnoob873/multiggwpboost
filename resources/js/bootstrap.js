import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['X-CSRF-TOKEN'] = window.appState?.csrfToken ?? '';
window.Pusher = Pusher;

const broadcastConfig = window.appState?.broadcast ?? {};
const broadcastPath = String(broadcastConfig.path || '').trim();
const normalizedBroadcastPath = broadcastPath === ''
  ? undefined
  : `/${broadcastPath.replace(/^\/+|\/+$/g, '')}`;

window.Echo = broadcastConfig.enabled && broadcastConfig.key && broadcastConfig.host
  ? new Echo({
      broadcaster: 'pusher',
      key: broadcastConfig.key,
      cluster: broadcastConfig.cluster || undefined,
      wsHost: broadcastConfig.host,
      wsPort: Number(broadcastConfig.port || 6001),
      wssPort: Number(broadcastConfig.port || 6001),
      forceTLS: Boolean(broadcastConfig.forceTLS),
      wsPath: normalizedBroadcastPath,
      disableStats: true,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: broadcastConfig.authEndpoint || '/broadcasting/auth',
      auth: {
        headers: {
          'X-CSRF-TOKEN': window.appState?.csrfToken ?? '',
        },
      },
    })
  : null;

window.axios.interceptors.request.use((config) => {
  const socketId = window.Echo?.socketId?.();

  if (socketId) {
    config.headers = config.headers ?? {};
    config.headers['X-Socket-ID'] = socketId;
  }

  return config;
});
