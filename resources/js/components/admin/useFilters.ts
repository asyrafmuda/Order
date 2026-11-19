import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * List filters kept in the query string. Changes are applied after a short
 * pause so typing in a search box does not fire a request per keystroke.
 */
export function useFilters<T extends Record<string, string>>(routeName: string, initial: T) {
    const [values, setValues] = useState<T>(initial);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = window.setTimeout(() => {
            const query = Object.fromEntries(Object.entries(values).filter(([, value]) => value !== ''));

            router.get(route(routeName), query, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [JSON.stringify(values)]);

    const set = <K extends keyof T>(key: K, value: string) => setValues((current) => ({ ...current, [key]: value }));
    const reset = () => setValues(Object.fromEntries(Object.keys(initial).map((key) => [key, ''])) as T);
    const active = Object.values(values).some((value) => value !== '');

    return { values, set, reset, active };
}
