import { X } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/cn';

interface ModalProps {
    open: boolean;
    onClose: () => void;
    title: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    size?: 'sm' | 'md' | 'lg';
}

const sizes = { sm: 'max-w-md', md: 'max-w-xl', lg: 'max-w-3xl' };

export function Modal({ open, onClose, title, children, footer, size = 'md' }: ModalProps) {
    useEffect(() => {
        if (!open) return;

        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = previous;
        };
    }, [open, onClose]);

    if (!open) return null;

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4">
            <div className="absolute inset-0 bg-black/70 backdrop-blur-sm" onClick={onClose} aria-hidden />
            <div
                role="dialog"
                aria-modal="true"
                className={cn(
                    'relative flex max-h-[92dvh] w-full animate-slide-up flex-col rounded-t-2xl border border-ink-700 bg-ink-850 shadow-2xl sm:rounded-2xl',
                    sizes[size],
                )}
            >
                <div className="flex items-center justify-between gap-4 border-b border-ink-700 px-5 py-4">
                    <h2 className="min-w-0 text-base font-bold text-white">{title}</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-ink-400 hover:bg-ink-700 hover:text-white"
                        aria-label="Close"
                    >
                        <X className="size-5" />
                    </button>
                </div>
                <div className="min-h-0 flex-1 overflow-y-auto px-5 py-4">{children}</div>
                {footer && <div className="flex flex-wrap items-center justify-end gap-2 border-t border-ink-700 px-5 py-4">{footer}</div>}
            </div>
        </div>,
        document.body,
    );
}
