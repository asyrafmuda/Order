import { useState, type ReactNode } from 'react';
import { Button } from './Button';
import { Modal } from './Modal';

interface ConfirmButtonProps {
    title: string;
    message: ReactNode;
    confirmLabel?: string;
    onConfirm: () => void;
    variant?: 'primary' | 'secondary' | 'ghost' | 'success' | 'danger' | 'flame' | 'outline';
    size?: 'xs' | 'sm' | 'md' | 'lg';
    icon?: ReactNode;
    disabled?: boolean;
    className?: string;
    children: ReactNode;
}

/** A button that asks "are you sure?" in a dialog before running the action. */
export function ConfirmButton({ title, message, confirmLabel = 'Confirm', onConfirm, children, variant = 'danger', ...props }: ConfirmButtonProps) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button variant={variant} onClick={() => setOpen(true)} {...props}>
                {children}
            </Button>
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title={title}
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setOpen(false)}>
                            Keep it
                        </Button>
                        <Button
                            variant="primary"
                            onClick={() => {
                                setOpen(false);
                                onConfirm();
                            }}
                        >
                            {confirmLabel}
                        </Button>
                    </>
                }
            >
                <div className="text-sm text-ink-300">{message}</div>
            </Modal>
        </>
    );
}
