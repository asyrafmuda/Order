import type { ComboOffer, Product } from '@/types';

/** One line on the counter ticket. Prices are previews; the server re-prices on submit. */
export interface PosLine {
    key: string;
    product: Product;
    variantId: number | null;
    modifierIds: number[];
    combo: boolean;
    comboChoice: string | null;
    notes: string;
    quantity: number;
}

export type PosLineInput = Omit<PosLine, 'key'>;

/** The product cannot be added until the cashier picks something (a size or a required option). */
export function needsChoice(product: Product): boolean {
    return (product.variants?.length ?? 0) > 0 || (product.modifier_groups ?? []).some((group) => group.min_select > 0);
}

export function hasOptions(product: Product, combo: ComboOffer | null): boolean {
    return (product.variants?.length ?? 0) > 0 || (product.modifier_groups?.length ?? 0) > 0 || (product.is_combo_eligible && combo !== null);
}

export function unitPrice(line: PosLineInput, combo: ComboOffer | null): number {
    const base = line.product.variants?.find((variant) => variant.id === line.variantId)?.price ?? line.product.price;
    const extras = (line.product.modifier_groups ?? [])
        .flatMap((group) => group.modifiers ?? [])
        .filter((modifier) => line.modifierIds.includes(modifier.id))
        .reduce((sum, modifier) => sum + modifier.price, 0);

    return base + extras + (line.combo && combo ? combo.price : 0);
}

/** Size, options and combo as short labels, in menu order. */
export function describe(line: PosLineInput, combo: ComboOffer | null): string[] {
    const variant = line.product.variants?.find((item) => item.id === line.variantId)?.name;
    const modifiers = (line.product.modifier_groups ?? [])
        .flatMap((group) => group.modifiers ?? [])
        .filter((modifier) => line.modifierIds.includes(modifier.id))
        .map((modifier) => modifier.name);

    return [variant, ...modifiers, line.combo && combo ? `${combo.name}${line.comboChoice ? `: ${line.comboChoice}` : ''}` : null].filter(
        (part): part is string => Boolean(part),
    );
}

/** Two lines are the same item when everything but the quantity matches. */
export function signature(line: PosLineInput): string {
    return JSON.stringify([line.product.id, line.variantId, [...line.modifierIds].sort((a, b) => a - b), line.combo, line.comboChoice, line.notes.trim()]);
}

/** Banknote amounts a customer is likely to hand over for this total, smallest first. */
export function cashSuggestions(total: number): number[] {
    const steps = [500, 1000, 2000, 5000, 10000];
    const amounts = steps.map((step) => Math.ceil(total / step) * step).filter((amount) => amount > total);

    return [...new Set(amounts)].slice(0, 4);
}
