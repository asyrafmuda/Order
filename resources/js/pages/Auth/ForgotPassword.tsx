import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Form';
import AuthLayout from '@/layouts/AuthLayout';

export default function ForgotPassword() {
    const form = useForm({ email: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('password.email'));
    };

    return (
        <AuthLayout
            title="Forgot your PIN?"
            subtitle="If you added an email to your account, we will send a link to choose a new one. No email on your account? Ask our crew at the counter — they can reset your PIN in a moment."
            footer={
                <Link href={route('login')} className="font-semibold text-flame-400 hover:underline">
                    Back to sign in
                </Link>
            }
        >
            <Head title="Forgot PIN" />
            <form onSubmit={submit} className="space-y-4">
                <Input label="Email" type="email" autoFocus required value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} error={form.errors.email} />
                <Button type="submit" size="lg" className="w-full" loading={form.processing}>
                    Email me a reset link
                </Button>
            </form>
        </AuthLayout>
    );
}
