import { Head, router } from '@inertiajs/react';
import { Bike, CircleCheckBig } from 'lucide-react';
import { RiderDeliveryCard, type RiderDelivery } from '@/components/staff/RiderDeliveryCard';
import { Card, EmptyState } from '@/components/ui/Card';
import { Toggle } from '@/components/ui/Form';
import { useLiveReload } from '@/hooks/useLiveReload';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { money, plural, time } from '@/lib/format';

interface RiderProps {
    rider: {
        id: number;
        vehicle_type: string;
        plate_number: string | null;
        is_available: boolean;
    };
    deliveries: RiderDelivery[];
    completed: RiderDelivery[];
}

export default function RiderIndex({ rider, deliveries, completed }: RiderProps) {
    useLiveReload([{ name: 'staff', private: true, perOutlet: true }], { only: ['rider', 'deliveries', 'completed'] });

    const setAvailability = (available: boolean) =>
        router.put(route('rider.availability'), { is_available: available }, { preserveScroll: true, preserveState: true });

    const earned = completed.reduce((sum, delivery) => sum + delivery.fee, 0);

    return (
        <BackOfficeLayout title="My Deliveries" description={`${plural(deliveries.length, 'open delivery', 'open deliveries')} · ${completed.length} delivered today`}>
            <Head title="My Deliveries" />

            <div className="mx-auto max-w-xl space-y-4">
                <Card className="flex items-center justify-between gap-4 p-4">
                    <Toggle
                        checked={rider.is_available}
                        onChange={setAvailability}
                        label={rider.is_available ? 'Available for deliveries' : 'Off the roster'}
                        description={rider.is_available ? 'The counter can assign you new orders.' : 'Switch on when you are ready to ride.'}
                    />
                    <p className="shrink-0 text-right text-xs text-ink-400">
                        <span className="flex items-center justify-end gap-1.5 font-semibold text-ink-200">
                            <Bike className="size-4 text-flame-400" aria-hidden />
                            {rider.vehicle_type}
                        </span>
                        {rider.plate_number}
                    </p>
                </Card>

                <section aria-labelledby="open-deliveries" className="space-y-3">
                    <h2 id="open-deliveries" className="text-[11px] font-extrabold tracking-[0.15em] text-ink-400 uppercase">
                        Your deliveries ({deliveries.length})
                    </h2>
                    {deliveries.length === 0 ? (
                        <Card>
                            <EmptyState icon={<Bike className="size-10" />} title="No deliveries assigned">
                                {rider.is_available
                                    ? 'New deliveries show up here as soon as the counter assigns one to you.'
                                    : 'You are off the roster. Switch availability on to receive deliveries.'}
                            </EmptyState>
                        </Card>
                    ) : (
                        deliveries.map((delivery) => <RiderDeliveryCard key={delivery.id} delivery={delivery} />)
                    )}
                </section>

                {completed.length > 0 && (
                    <section aria-labelledby="completed-deliveries" className="space-y-3">
                        <h2 id="completed-deliveries" className="flex items-center justify-between text-[11px] font-extrabold tracking-[0.15em] text-ink-400 uppercase">
                            <span>Delivered today ({completed.length})</span>
                            <span className="tracking-normal text-ink-300 normal-case">Delivery fees {money(earned)}</span>
                        </h2>
                        <Card>
                            <ul className="divide-y divide-ink-700">
                                {completed.map((delivery) => (
                                    <li key={delivery.id} className="flex items-center gap-3 px-4 py-3">
                                        <CircleCheckBig className="size-5 shrink-0 text-go-400" aria-hidden />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-bold text-strong">
                                                {delivery.order.order_number} · {delivery.recipient_name}
                                            </p>
                                            <p className="truncate text-xs text-ink-400">{delivery.address}</p>
                                        </div>
                                        <span className="shrink-0 text-xs font-semibold text-ink-300">{time(delivery.delivered_at)}</span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    </section>
                )}
            </div>
        </BackOfficeLayout>
    );
}
