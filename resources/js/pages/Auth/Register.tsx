import { Head, Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Form';
import AuthLayout from '@/layouts/AuthLayout';

export default function Register() {
    const form = useForm({ name: '', phone: '', pin: '', email: '' });
    const [withEmail, setWithEmail] = useState(false);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('register.store'), { onFinish: () => form.reset('pin') });
    };

    return (
        <AuthLayout
            title="Join the crew"
            subtitle="Three things and you are in: your name, mobile number and a PIN."
            footer={
                <>
                    Already have an account?{' '}
                    <Link href={route('login')} className="font-semibold text-flame-400 hover:underline">
                        Sign in
                    </Link>
                </>
            }
        >
            <Head title="Create account" />
            <form onSubmit={submit} className="space-y-4">
                <Input label="Name" autoComplete="name" autoFocus required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} error={form.errors.name} />
                <Input
                    label="Mobile number"
                    type="tel"
                    autoComplete="tel"
                    placeholder="012-345 6789"
                    hint="This is how you sign in, and where we send order updates."
                    required
                    value={form.data.phone}
                    onChange={(event) => form.setData('phone', event.target.value)}
                    error={form.errors.phone}
                />
                <Input
                    label="Choose a 6-digit PIN"
                    type="password"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    maxLength={6}
                    autoComplete="new-password"
                    placeholder="••••••"
                    required
                    value={form.data.pin}
                    onChange={(event) => form.setData('pin', event.target.value.replace(/\D/g, ''))}
                    error={form.errors.pin}
                    className="font-mono tracking-[0.5em]"
                />

                {withEmail ? (
                    <Input
                        label="Email (optional)"
                        type="email"
                        autoComplete="email"
                        hint="Only used to reset your PIN if you forget it."
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                        error={form.errors.email}
                    />
                ) : (
                    <button type="button" onClick={() => setWithEmail(true)} className="text-xs font-semibold text-ink-400 hover:text-strong">
                        + Add an email so you can reset a forgotten PIN yourself
                    </button>
                )}

                <Button type="submit" size="lg" className="w-full" loading={form.processing}>
                    Create account
                </Button>
                <p className="text-center text-[11px] text-ink-500">
                    By joining you agree to our{' '}
                    <Link href={route('legal.terms')} className="underline hover:text-ink-200">
                        Terms
                    </Link>{' '}
                    and{' '}
                    <Link href={route('legal.privacy')} className="underline hover:text-ink-200">
                        Privacy Notice
                    </Link>
                    .
                </p>
            </form>
        </AuthLayout>
    );
}
