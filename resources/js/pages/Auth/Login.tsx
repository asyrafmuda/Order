import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { Checkbox, Input } from '@/components/ui/Form';
import AuthLayout from '@/layouts/AuthLayout';

const demoAccounts = [
    ['Admin', 'admin@bossburger.test'],
    ['Cashier', 'cashier@bossburger.test'],
    ['Kitchen', 'kitchen@bossburger.test'],
    ['Rider', 'rider@bossburger.test'],
    ['Customer', 'customer@bossburger.test'],
];

export default function Login({ demo }: { demo: boolean }) {
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('login.store'), { onFinish: () => form.reset('password') });
    };

    return (
        <AuthLayout
            title="Sign in"
            subtitle="Track your orders, reorder favourites, or open your staff workspace."
            footer={
                <>
                    New here?{' '}
                    <Link href={route('register')} className="font-semibold text-flame-400 hover:underline">
                        Create an account
                    </Link>
                </>
            }
        >
            <Head title="Sign in" />
            <form onSubmit={submit} className="space-y-4">
                <Input
                    label="Email"
                    type="email"
                    autoComplete="username"
                    autoFocus
                    required
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    error={form.errors.email}
                />
                <Input
                    label="Password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    error={form.errors.password}
                />
                <div className="flex items-center justify-between">
                    <Checkbox label="Remember me" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} />
                    <Link href={route('password.request')} className="text-sm text-ink-400 hover:text-white">
                        Forgot password?
                    </Link>
                </div>
                <Button type="submit" size="lg" className="w-full" loading={form.processing}>
                    Sign in
                </Button>
            </form>

            {demo && (
                <div className="mt-6 rounded-lg border border-dashed border-ink-600 p-3">
                    <p className="text-xs font-semibold text-ink-400">Demo accounts (password: “password”)</p>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {demoAccounts.map(([label, email]) => (
                            <button
                                key={email}
                                type="button"
                                onClick={() => form.setData({ email, password: 'password', remember: false })}
                                className="rounded-md bg-ink-700 px-2 py-1 text-xs font-semibold text-ink-200 hover:bg-ink-600"
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </AuthLayout>
    );
}
