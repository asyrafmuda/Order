import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * The Laravel Reverb connection, opened the first time a live screen asks for
 * it. Returns undefined when no key was built in; screens then fall back to
 * polling (see useLiveReload).
 */
export function getEcho(): Window['Echo'] {
    const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;

    if (!key || window.Echo) {
        return window.Echo;
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

    return window.Echo;
}
