import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Form';
import AuthLayout from '@/layouts/AuthLayout';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('password.update'), { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    return (
        <AuthLayout title="Choose a new password">
            <Head title="New password" />
            <form onSubmit={submit} className="space-y-4">
                <Input label="Email" type="email" required value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} error={form.errors.email} />
                <Input label="New password" type="password" autoComplete="new-password" autoFocus required value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} error={form.errors.password} />
                <Input
                    label="Confirm new password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.data.password_confirmation}
                    onChange={(event) => form.setData('password_confirmation', event.target.value)}
                    error={form.errors.password_confirmation}
                />
                <Button type="submit" size="lg" className="w-full" loading={form.processing}>
                    Save new password
                </Button>
            </form>
        </AuthLayout>
    );
}
