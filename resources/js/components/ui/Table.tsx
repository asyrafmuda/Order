import type { HTMLAttributes, TdHTMLAttributes, ThHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

export function Table({ className, ...props }: HTMLAttributes<HTMLTableElement>) {
    return (
        <div className="relative overflow-x-auto">
            <table className={cn('w-full min-w-max text-left text-sm', className)} {...props} />
        </div>
    );
}

export function Th({ className, ...props }: ThHTMLAttributes<HTMLTableCellElement>) {
    return (
        <th
            className={cn('border-b border-ink-700 px-4 py-2.5 text-[11px] font-bold tracking-wider text-ink-400 uppercase', className)}
            {...props}
        />
    );
}

export function Td({ className, ...props }: TdHTMLAttributes<HTMLTableCellElement>) {
    return <td className={cn('border-b border-ink-800 px-4 py-3 align-middle text-ink-200', className)} {...props} />;
}
