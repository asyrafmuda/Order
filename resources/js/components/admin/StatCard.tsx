import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

interface StatCardProps {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    icon?: ReactNode;
    tone?: 'default' | 'flame' | 'green' | 'red';
}

const tones = {
    default: 'text-strong',
    flame: 'text-flame-400',
    green: 'text-go-400',
    red: 'text-danger-400',
};

/** A headline number on the dashboard and reports. */
export function StatCard({ label, value, hint, icon, tone = 'default' }: StatCardProps) {
    return (
        <div className="rounded-xl border border-ink-700 bg-ink-850 p-4">
            <div className="flex items-center justify-between gap-2">
                <p className="text-[11px] font-bold tracking-wider text-ink-400 uppercase">{label}</p>
                {icon && <span className="text-ink-500">{icon}</span>}
            </div>
            <p className={cn('mt-2 font-display text-[2rem] leading-none whitespace-nowrap', tones[tone])}>{value}</p>
            {hint && <p className="mt-1.5 text-xs text-ink-400">{hint}</p>}
        </div>
    );
}
