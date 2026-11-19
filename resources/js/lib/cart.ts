import { router } from '@inertiajs/react';
import type { OrderType } from '@/types';

const keep = { preserveScroll: true, preserveState: true } as const;

export interface AddToCartInput {
    product_id: number;
    variant_id?: number | null;
    modifier_ids?: number[];
    combo?: boolean;
    combo_choice?: string | null;
    quantity?: number;
    notes?: string | null;
}

/** Cart mutations. Each one round-trips to the server, which re-prices the ticket. */
export const cartActions = {
    add(input: AddToCartInput, onSuccess?: () => void) {
        router.post(route('cart.store'), { ...input }, { ...keep, onSuccess });
    },
    setQuantity(lineId: string, quantity: number) {
        router.patch(route('cart.update', lineId), { quantity }, keep);
    },
    remove(lineId: string) {
        router.delete(route('cart.destroy', lineId), keep);
    },
    upgradeToCombo(lineId: string, comboChoice?: string | null) {
        router.post(route('cart.combo', lineId), { combo_choice: comboChoice ?? null }, keep);
    },
    clear() {
        router.delete(route('cart.clear'), keep);
    },
    setType(type: OrderType) {
        router.put(route('cart.preferences'), { type }, keep);
    },
    setZone(zoneId: number) {
        router.put(route('cart.preferences'), { zone_id: zoneId }, keep);
    },
};
