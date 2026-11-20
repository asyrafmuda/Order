import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { Button } from '@/components/ui/Button';
import type { ShopCode } from './Index';

const steps = ['Scan with your phone camera', 'Pick your food and send the order', 'Pay at the counter or online', 'Collect when your number is called'];

/**
 * A print-ready A4 poster with the outlet's shop QR code, for the counter or
 * the front of a kiosk.
 */
export default function ShopPoster({ shopCode }: { shopCode: ShopCode }) {
    const { shop, outlet } = usePage().props;

    return (
        <div className="min-h-dvh bg-neutral-200 text-neutral-900 print:bg-white">
            <Head title="Shop QR poster" />

            <div className="no-print sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-neutral-300 bg-white px-4 py-3">
                <Link href={route('admin.tables.index')} className="flex items-center gap-1.5 text-sm font-semibold text-neutral-700 hover:text-black">
                    <ArrowLeft className="size-4" aria-hidden /> Back
                </Link>
                <p className="hidden text-sm text-neutral-500 sm:block">A4 poster · stick it at the counter where customers queue</p>
                <Button onClick={() => window.print()} icon={<Printer className="size-4" aria-hidden />}>
                    Print
                </Button>
            </div>

            {!shopCode.usable && (
                <p className="no-print mx-auto mt-4 max-w-[210mm] rounded-lg bg-amber-100 px-4 py-3 text-sm font-medium text-amber-900">
                    Scanning this code does nothing right now: the outlet is inactive or self pickup is switched off.
                </p>
            )}

            <div className="mx-auto max-w-[210mm] p-4 print:p-0">
                <section className="flex flex-col items-center rounded-3xl border-2 border-dashed border-neutral-400 bg-white px-8 py-12 text-center print:min-h-[277mm] print:justify-center print:rounded-none print:border-0">
                    <p className="text-sm font-extrabold tracking-[0.3em] text-red-600 uppercase">
                        {shop.name}
                        {outlet.multi ? ` · ${shopCode.outlet}` : ''}
                    </p>
                    <p className="mt-3 font-display text-7xl leading-none uppercase sm:text-8xl">Scan to order</p>
                    <p className="mt-3 text-lg font-semibold text-neutral-700">Skip the queue. No app, no sign-up.</p>

                    <div className="my-8 rounded-3xl border-4 border-neutral-900 p-4">
                        <QRCodeSVG value={shopCode.url} size={300} level="M" marginSize={1} aria-label="QR code to order at this shop" />
                    </div>

                    <ol className="grid w-full max-w-xl gap-3 text-left sm:grid-cols-2">
                        {steps.map((step, index) => (
                            <li key={step} className="flex items-center gap-3 rounded-xl bg-neutral-100 px-4 py-3">
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-neutral-900 text-sm font-extrabold text-white">{index + 1}</span>
                                <span className="text-sm leading-snug font-semibold">{step}</span>
                            </li>
                        ))}
                    </ol>
                    <p className="mt-8 text-xs break-all text-neutral-500">{shopCode.url.replace(/^https?:\/\//, '')}</p>
                </section>
            </div>
        </div>
    );
}
