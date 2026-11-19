import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Form';
import AuthLayout from '@/layouts/AuthLayout';

export default function Register() {
    const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('register.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    return (
        <AuthLayout
            title="Join the crew"
            subtitle="Save your details, keep your order history and check out faster."
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
                <Input label="Full name" autoComplete="name" autoFocus required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} error={form.errors.name} />
                <Input label="Email" type="email" autoComplete="email" required value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} error={form.errors.email} />
                <Input
                    label="Mobile number"
                    type="tel"
                    autoComplete="tel"
                    placeholder="012-345 6789"
                    hint="We send order updates to this number."
                    required
                    value={form.data.phone}
                    onChange={(event) => form.setData('phone', event.target.value)}
                    error={form.errors.phone}
                />
                <Input label="Password" type="password" autoComplete="new-password" required value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} error={form.errors.password} />
                <Input
                    label="Confirm password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.data.password_confirmation}
                    onChange={(event) => form.setData('password_confirmation', event.target.value)}
                    error={form.errors.password_confirmation}
                />
                <Button type="submit" size="lg" className="w-full" loading={form.processing}>
                    Create account
                </Button>
            </form>
        </AuthLayout>
    );
}
