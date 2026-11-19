import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/cn';

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('rounded-xl border border-ink-700 bg-ink-850', className)} {...props} />;
}

interface CardHeaderProps {
    title: ReactNode;
    icon?: ReactNode;
    action?: ReactNode;
    className?: string;
}

export function CardHeader({ title, icon, action, className }: CardHeaderProps) {
    return (
        <div className={cn('flex items-center justify-between gap-3 border-b border-ink-700 px-4 py-3', className)}>
            <h2 className="flex min-w-0 items-center gap-2 text-sm font-bold text-white">
                {icon && <span className="shrink-0 text-flame-400">{icon}</span>}
                <span className="truncate">{title}</span>
            </h2>
            {action && <div className="shrink-0">{action}</div>}
        </div>
    );
}

export function EmptyState({ icon, title, children }: { icon?: ReactNode; title: string; children?: ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
            {icon && <div className="text-ink-500">{icon}</div>}
            <p className="font-semibold text-ink-200">{title}</p>
            {children && <div className="max-w-sm text-sm text-ink-400">{children}</div>}
        </div>
    );
}
