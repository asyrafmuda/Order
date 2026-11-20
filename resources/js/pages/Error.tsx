import { Head, Link } from '@inertiajs/react';
import { buttonClass } from '@/components/ui/Button';

const messages: Record<number, [string, string]> = {
    403: ['Staff only', 'You do not have access to this page.'],
    404: ['Page not found', 'That page is no longer on the menu.'],
    500: ['Something went wrong', 'An unexpected error happened on our side. Please try again.'],
    503: ['Back shortly', 'We are doing a quick clean-up. Please check back in a few minutes.'],
};

export default function Error({ status }: { status: number }) {
    const [title, description] = messages[status] ?? messages[500];

    return (
        <div className="flex min-h-dvh flex-col items-center justify-center gap-3 px-4 text-center">
            <Head title={title} />
            <p className="font-display text-8xl text-brand-500">{status}</p>
            <h1 className="font-display text-3xl text-white uppercase">{title}</h1>
            <p className="max-w-sm text-sm text-ink-400">{description}</p>
            <Link href="/" className={buttonClass('primary', 'md', 'mt-4')}>
                Back to the menu
            </Link>
        </div>
    );
}
