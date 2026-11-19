/** Format an amount in sen as ringgit, e.g. 2880 → "RM 28.80". */
export function money(sen: number, symbol = true): string {
    const value = (sen / 100).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    return symbol ? `RM ${value}` : value;
}

/** Ringgit string for form inputs, e.g. 2880 → "28.80". */
export function senToInput(sen: number | null | undefined): string {
    return sen == null ? '' : (sen / 100).toFixed(2);
}

/** Parse a ringgit form input to sen, e.g. "28.8" → 2880. */
export function inputToSen(value: string): number {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? Math.round(parsed * 100) : 0;
}

/**
 * Times are always shown in the shop's timezone (set by the server in a meta
 * tag), so a ticket reads the same on every device.
 */
const timeZone = typeof document === 'undefined' ? undefined : (document.querySelector<HTMLMetaElement>('meta[name="shop-timezone"]')?.content ?? undefined);

export function time(iso: string | null | undefined): string {
    if (!iso) return '—';

    return new Date(iso).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true, timeZone });
}

export function date(iso: string | null | undefined): string {
    if (!iso) return '—';

    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', timeZone });
}

export function dateTime(iso: string | null | undefined): string {
    if (!iso) return '—';

    return `${date(iso)}, ${time(iso)}`;
}

/** Whole minutes from now until the given time (negative when in the past). */
export function minutesUntil(iso: string | null | undefined, now: number = Date.now()): number | null {
    if (!iso) return null;

    return Math.round((new Date(iso).getTime() - now) / 60000);
}

/** Whole minutes elapsed since the given time. */
export function minutesSince(iso: string | null | undefined, now: number = Date.now()): number | null {
    if (!iso) return null;

    return Math.max(0, Math.floor((now - new Date(iso).getTime()) / 60000));
}

export function plural(count: number, singular: string, pluralForm = `${singular}s`): string {
    return `${count} ${count === 1 ? singular : pluralForm}`;
}
