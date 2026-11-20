import { usePage } from '@inertiajs/react';
import { CheckCircle2, TriangleAlert, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/cn';

interface Toast {
    id: number;
    kind: 'success' | 'error';
    message: string;
}

/** Shows session flash messages ("success" / "error") as dismissible toasts. */
export function Flash() {
    const { flash } = usePage().props;
    const [toasts, setToasts] = useState<Toast[]>([]);

    useEffect(() => {
        const incoming: Toast[] = [];
        if (flash.success) incoming.push({ id: Date.now(), kind: 'success', message: flash.success });
        if (flash.error) incoming.push({ id: Date.now() + 1, kind: 'error', message: flash.error });
        if (incoming.length === 0) return;

        setToasts((current) => [...current, ...incoming]);
        const ids = incoming.map((toast) => toast.id);
        const timer = window.setTimeout(() => setToasts((current) => current.filter((toast) => !ids.includes(toast.id))), incoming.some((toast) => toast.kind === 'error') ? 6000 : 3000);

        return () => window.clearTimeout(timer);
    }, [flash]);

    if (toasts.length === 0) return null;

    return (
        <div className="no-print pointer-events-none fixed inset-x-0 top-3 z-[60] flex flex-col items-center gap-2 px-4 lg:top-auto lg:bottom-4" aria-live="polite">
            {/* Only the latest two: a burst of taps must not bury the screen. */}
            {toasts.slice(-2).map((toast) => (
                <div
                    key={toast.id}
                    role="status"
                    className={cn(
                        'pointer-events-auto flex w-full max-w-md animate-slide-up items-start gap-3 rounded-xl border px-4 py-3 text-sm font-medium shadow-2xl',
                        toast.kind === 'success' ? 'border-go-500/40 bg-go-900 text-go-400' : 'border-danger-600/50 bg-danger-900 text-danger-400',
                    )}
                >
                    {toast.kind === 'success' ? <CheckCircle2 className="mt-0.5 size-4 shrink-0" /> : <TriangleAlert className="mt-0.5 size-4 shrink-0" />}
                    <span className="min-w-0 flex-1 text-ink-100">{toast.message}</span>
                    <button
                        type="button"
                        onClick={() => setToasts((current) => current.filter((item) => item.id !== toast.id))}
                        className="text-ink-400 hover:text-strong"
                        aria-label="Dismiss"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            ))}
        </div>
    );
}
