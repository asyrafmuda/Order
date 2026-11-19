import { useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

const control =
    'w-full rounded-lg border border-ink-600 bg-ink-900 px-3 text-sm text-ink-100 placeholder:text-ink-500 transition-colors focus:border-flame-400 focus:outline-none disabled:opacity-60';

interface FieldProps {
    label?: ReactNode;
    error?: string;
    hint?: ReactNode;
    required?: boolean;
    className?: string;
    /** Render-prop receives the generated id so the label is linked to the control. */
    children: (id: string) => ReactNode;
}

export function Field({ label, error, hint, required, className, children }: FieldProps) {
    const id = useId();

    return (
        <div className={cn('space-y-1.5', className)}>
            {label && (
                <label htmlFor={id} className="block text-xs font-semibold text-ink-300">
                    {label}
                    {required && <span className="ml-0.5 text-brand-400">*</span>}
                </label>
            )}
            {children(id)}
            {error ? (
                <p className="text-xs font-medium text-brand-400" role="alert">
                    {error}
                </p>
            ) : (
                hint && <p className="text-xs text-ink-400">{hint}</p>
            )}
        </div>
    );
}

interface ControlProps {
    label?: ReactNode;
    error?: string;
    hint?: ReactNode;
    wrapperClassName?: string;
}

export function Input({
    label,
    error,
    hint,
    wrapperClassName,
    className,
    prefix,
    ...props
}: Omit<InputHTMLAttributes<HTMLInputElement>, 'prefix'> & ControlProps & { prefix?: ReactNode }) {
    return (
        <Field label={label} error={error} hint={hint} required={props.required} className={wrapperClassName}>
            {(id) =>
                prefix ? (
                    <div className="relative">
                        <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-ink-400">{prefix}</span>
                        <input id={id} className={cn(control, 'h-10 pl-11', error && 'border-brand-500', className)} {...props} />
                    </div>
                ) : (
                    <input id={id} className={cn(control, 'h-10', error && 'border-brand-500', className)} {...props} />
                )
            }
        </Field>
    );
}

export function Textarea({ label, error, hint, wrapperClassName, className, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement> & ControlProps) {
    return (
        <Field label={label} error={error} hint={hint} required={props.required} className={wrapperClassName}>
            {(id) => <textarea id={id} rows={3} className={cn(control, 'py-2', error && 'border-brand-500', className)} {...props} />}
        </Field>
    );
}

export function Select({
    label,
    error,
    hint,
    wrapperClassName,
    className,
    children,
    ...props
}: SelectHTMLAttributes<HTMLSelectElement> & ControlProps) {
    return (
        <Field label={label} error={error} hint={hint} required={props.required} className={wrapperClassName}>
            {(id) => (
                <select id={id} className={cn(control, 'h-10', error && 'border-brand-500', className)} {...props}>
                    {children}
                </select>
            )}
        </Field>
    );
}

interface ToggleProps {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: ReactNode;
    description?: ReactNode;
    disabled?: boolean;
}

export function Toggle({ checked, onChange, label, description, disabled }: ToggleProps) {
    return (
        <label className={cn('flex items-start gap-3', disabled ? 'opacity-60' : 'cursor-pointer')}>
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                disabled={disabled}
                onClick={() => onChange(!checked)}
                className={cn(
                    'relative mt-0.5 h-6 w-11 shrink-0 rounded-full transition-colors',
                    checked ? 'bg-go-500' : 'bg-ink-600',
                )}
            >
                <span className={cn('absolute top-0.5 left-0.5 size-5 rounded-full bg-white transition-transform', checked && 'translate-x-5')} />
            </button>
            <span className="min-w-0">
                <span className="block text-sm font-semibold text-ink-100">{label}</span>
                {description && <span className="block text-xs text-ink-400">{description}</span>}
            </span>
        </label>
    );
}

interface CheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
    label: ReactNode;
}

export function Checkbox({ label, className, ...props }: CheckboxProps) {
    return (
        <label className={cn('flex cursor-pointer items-center gap-2 text-sm text-ink-200', className)}>
            <input type="checkbox" className="size-4 rounded border-ink-500 bg-ink-900 accent-brand-600" {...props} />
            {label}
        </label>
    );
}
