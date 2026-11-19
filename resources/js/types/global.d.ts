import type { route as ziggyRoute } from 'ziggy-js';
import type Echo from 'laravel-echo';
import type { SharedProps } from '@/types';

declare global {
    const route: typeof ziggyRoute;

    interface Window {
        Echo?: Echo<'reverb'>;
        Pusher?: unknown;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}
