import type { InputHTMLAttributes, ReactNode } from 'react';
import { Input } from '@/components/ui/Form';

interface MoneyInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'value' | 'onChange' | 'prefix'> {
    label?: ReactNode;
    error?: string;
    hint?: ReactNode;
    wrapperClassName?: string;
    /** Ringgit as typed, e.g. "12.90". Convert with inputToSen() before submitting. */
    value: string;
    onChange: (value: string) => void;
}

/** A ringgit amount field. Forms keep the typed string and send integer sen. */
export function MoneyInput({ value, onChange, ...props }: MoneyInputProps) {
    return (
        <Input
            type="number"
            inputMode="decimal"
            min="0"
            step="0.01"
            prefix="RM"
            placeholder="0.00"
            value={value}
            onChange={(event) => onChange(event.target.value)}
            {...props}
        />
    );
}
