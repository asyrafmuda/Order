import { Printer } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { printReceipt, type ReceiptCopies } from '@/lib/receipt';

interface Props {
    uuid: string;
    /** "receipt" for reprints; "order" also prints the kitchen slip when that is switched on. */
    copies?: ReceiptCopies;
    label?: string;
    variant?: 'primary' | 'secondary' | 'outline' | 'ghost';
    size?: 'xs' | 'sm' | 'md';
    className?: string;
}

/** Sends an order's receipt to the device's printer (thermal paper, 58 or 80 mm). */
export function PrintReceiptButton({ uuid, copies = 'receipt', label = 'Print receipt', variant = 'ghost', size = 'sm', className }: Props) {
    const [printing, setPrinting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const print = () => {
        setPrinting(true);
        setError(null);
        printReceipt(uuid, copies)
            .catch((reason: Error) => setError(reason.message))
            .finally(() => setPrinting(false));
    };

    return (
        <>
            <Button variant={variant} size={size} className={className} onClick={print} loading={printing} icon={<Printer className="size-4" aria-hidden />}>
                {label}
            </Button>
            {error && (
                <span className="text-xs font-medium text-danger-400" role="alert">
                    {error}
                </span>
            )}
        </>
    );
}
