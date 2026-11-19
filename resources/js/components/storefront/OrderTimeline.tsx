import { Check } from 'lucide-react';
import { cn } from '@/lib/cn';
import { time } from '@/lib/format';
import type { TimelineStep } from '@/types';

/** The "Live Order Lifecycle" strip: one cell per step, the current one highlighted. */
export function OrderTimeline({ steps }: { steps: TimelineStep[] }) {
    return (
        <ol className="scrollbar-none flex gap-2 overflow-x-auto pb-1">
            {steps.map((step, index) => (
                <li
                    key={step.key}
                    aria-current={step.state === 'active' ? 'step' : undefined}
                    className={cn(
                        'min-w-36 flex-1 rounded-lg border px-3 py-2.5',
                        step.state === 'active' && 'border-brand-500 bg-brand-600 text-white shadow-glow',
                        step.state === 'done' && 'border-go-500/30 bg-go-900/60',
                        step.state === 'upcoming' && 'border-ink-700 bg-ink-900 opacity-60',
                    )}
                >
                    <p className={cn('flex items-center justify-between text-[10px] font-extrabold tracking-wider uppercase', step.state === 'done' ? 'text-go-400' : step.state === 'active' ? 'text-white/80' : 'text-ink-400')}>
                        {step.state === 'active' ? 'Active step' : `Step ${String(index + 1).padStart(2, '0')}`}
                        {step.state === 'done' && <Check className="size-3.5" aria-label="Done" />}
                        {step.state === 'active' && <span className="size-2 animate-pulse-soft rounded-full bg-white" aria-hidden />}
                    </p>
                    <p className="mt-1 text-sm leading-tight font-bold text-white">{step.label}</p>
                    <p className={cn('text-[11px]', step.state === 'active' ? 'text-white/80' : 'text-ink-400')}>{step.at ? `${step.detail} · ${time(step.at)}` : step.detail}</p>
                </li>
            ))}
        </ol>
    );
}
