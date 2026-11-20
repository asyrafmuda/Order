import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { getEcho } from '@/echo';

interface LiveChannel {
    name: string;
    private?: boolean;
}

interface Options {
    /** Only these props are re-fetched from the server. */
    only: string[];
    /** Polling interval in ms, used when websockets are unavailable (and as a safety net when they are). */
    interval?: number;
    enabled?: boolean;
}

/**
 * Keep a page fresh: reload the given props whenever an "order.updated"
 * broadcast arrives on one of the channels. Falls back to polling when
 * Reverb is not configured.
 */
export function useLiveReload(channels: LiveChannel[], { only, interval = 8000, enabled = true }: Options): void {
    const { realtime } = usePage().props;
    const onlyRef = useRef(only);
    onlyRef.current = only;

    const channelKey = channels.map((channel) => `${channel.private ? 'p:' : ''}${channel.name}`).join('|');

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let timer: number | undefined;
        const reload = () => {
            // Coalesce bursts of events into a single request.
            window.clearTimeout(timer);
            timer = window.setTimeout(() => {
                if (document.visibilityState === 'visible') {
                    router.reload({ only: onlyRef.current });
                }
            }, 150);
        };

        // Only open a websocket when the server has broadcasting switched on.
        const echo = realtime ? getEcho() : undefined;

        if (echo) {
            channels.forEach((channel) => {
                (channel.private ? echo.private(channel.name) : echo.channel(channel.name)).listen('.order.updated', reload);
            });
        }

        const poll = window.setInterval(reload, echo ? Math.max(interval * 4, 30000) : interval);
        const onVisible = () => document.visibilityState === 'visible' && reload();
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            window.clearTimeout(timer);
            window.clearInterval(poll);
            document.removeEventListener('visibilitychange', onVisible);

            if (echo) {
                channels.forEach((channel) => echo.leave(channel.name));
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [channelKey, realtime, interval, enabled]);
}
