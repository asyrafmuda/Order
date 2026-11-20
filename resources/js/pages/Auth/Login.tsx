import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { Checkbox, Input } from '@/components/ui/Form';
import AuthLayout from '@/layouts/AuthLayout';

const demoAccounts = [
    ['Customer', '012-345 6789', '135790'],
    ['Admin', 'admin@bossburger.test', 'password'],
    ['Cashier', 'cashier@bossburger.test', 'password'],
    ['Waiter', 'waiter@bossburger.test', 'password'],
    ['Kitchen', 'kitchen@bossburger.test', 'password'],
    ['Rider', 'rider@bossburger.test', 'password'],
];

export default function Login({ demo }: { demo: boolean }) {
    const form = useForm({ login: '', password: '', remember: true });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('login.store'), { onFinish: () => form.reset('password') });
    };

    return (
        <AuthLayout
            title="Sign in"
            subtitle="Just your mobile number and PIN. No email needed."
            footer={
                <>
                    First time here?{' '}
                    <Link href={route('register')} className="font-semibold text-flame-400 hover:underline">
                        Create an account in 20 seconds
                    </Link>
                </>
            }
        >
            <Head title="Sign in" />
            <form onSubmit={submit} className="space-y-4">
                <Input
                    label="Mobile number"
                    hint="Crew: sign in with your email."
                    autoComplete="username"
                    inputMode="email"
                    placeholder="012-345 6789"
                    autoFocus
                    required
                    value={form.data.login}
                    onChange={(event) => form.setData('login', event.target.value)}
                    error={form.errors.login}
                />
                <Input
                    label="PIN"
                    type="password"
                    autoComplete="current-password"
                    placeholder="6 digits"
                    required
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    error={form.errors.password}
                />
                <div className="flex items-center justify-between">
                    <Checkbox label="Keep me signed in" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} />
                    <Link href={route('password.request')} className="text-sm text-ink-400 hover:text-white">
                        Forgot PIN?
                    </Link>
                </div>
                <Button type="submit" size="lg" className="w-full" loading={form.processing}>
                    Sign in
                </Button>
            </form>

            {demo && (
                <div className="mt-6 rounded-lg border border-dashed border-ink-600 p-3">
                    <p className="text-xs font-semibold text-ink-400">Demo accounts (tap to fill)</p>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {demoAccounts.map(([label, login, password]) => (
                            <button
                                key={label}
                                type="button"
                                onClick={() => form.setData({ login, password, remember: false })}
                                className="rounded-md bg-ink-700 px-2.5 py-1.5 text-xs font-semibold text-ink-200 hover:bg-ink-600"
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
