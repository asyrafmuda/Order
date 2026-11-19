import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Connect to Laravel Reverb when it is configured. Without a key the app
 * still works: live screens fall back to polling (see useLiveReload).
 */
export function bootEcho(): void {
    const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;

    if (!key || window.Echo) {
        return;
    }

    const scheme = (import.meta.env.VITE_REVERB_SCHEME as string | undefined) ?? 'http';
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);

    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: (import.meta.env.VITE_REVERB_HOST as string | undefined) ?? window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
