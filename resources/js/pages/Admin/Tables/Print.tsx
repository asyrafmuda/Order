import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { Button } from '@/components/ui/Button';
import type { DiningTable } from './Index';

/**
 * Print-ready table cards: white, six to an A4 page. Cut them out and put one
 * on each table.
 */
export default function TablesPrint({ tables }: { tables: DiningTable[] }) {
    const { shop } = usePage().props;

    return (
        <div className="min-h-dvh bg-neutral-200 text-neutral-900 print:bg-white">
            <Head title="Table QR codes" />

            <div className="no-print sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-neutral-300 bg-white px-4 py-3">
                <Link href={route('admin.tables.index')} className="flex items-center gap-1.5 text-sm font-semibold text-neutral-700 hover:text-black">
                    <ArrowLeft className="size-4" aria-hidden /> Back to tables
                </Link>
                <p className="hidden text-sm text-neutral-500 sm:block">{tables.length} active table{tables.length === 1 ? '' : 's'} · A4, six cards per page</p>
                <Button onClick={() => window.print()} icon={<Printer className="size-4" aria-hidden />}>
                    Print
                </Button>
            </div>

            {tables.length === 0 ? (
                <p className="p-10 text-center text-sm text-neutral-600">There are no active tables to print.</p>
            ) : (
                <div className="mx-auto grid max-w-[210mm] grid-cols-2 gap-4 p-4 print:gap-0 print:p-0">
                    {tables.map((table) => (
                        <section key={table.id} className="flex break-inside-avoid flex-col items-center rounded-2xl border-2 border-dashed border-neutral-400 bg-white px-6 py-7 text-center print:rounded-none">
                            <p className="text-xs font-extrabold tracking-[0.25em] text-red-600 uppercase">{shop.name}</p>
                            <p className="mt-1 font-display text-5xl leading-none uppercase">{table.name}</p>
                            <div className="my-4">
                                <QRCodeSVG value={table.url} size={190} level="M" marginSize={1} aria-label={`QR code for ${table.name}`} />
                            </div>
                            <p className="text-lg leading-tight font-extrabold">Scan to order</p>
                            <p className="mt-1 max-w-56 text-xs leading-snug text-neutral-600">Point your phone camera here, pick your food, and we will bring it to this table. No app or sign-up needed.</p>
                        </section>
                    ))}
                </div>
            )}
        </div>
    );
}
