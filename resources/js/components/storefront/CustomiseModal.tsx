import { Minus, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { cn } from '@/lib/cn';
import { cartActions } from '@/lib/cart';
import { money } from '@/lib/format';
import type { ComboOffer, ModifierGroup, Product } from '@/types';

interface CustomiseModalProps {
    product: Product;
    combo: ComboOffer | null;
    /** Pre-tick the combo upgrade (opened from the hot deal banner). */
    preselectCombo?: boolean;
    onClose: () => void;
}

/** Choose size, options, combo upgrade, quantity and notes before adding to the ticket. */
export function CustomiseModal({ product, combo, preselectCombo = false, onClose }: CustomiseModalProps) {
    const variants = product.variants ?? [];
    const groups = (product.modifier_groups ?? []).filter((group) => (group.modifiers ?? []).some((modifier) => modifier.is_available));
    const comboAvailable = combo !== null && product.is_combo_eligible;

    const [variantId, setVariantId] = useState<number | null>(() => (variants.find((variant) => variant.is_default) ?? variants[0])?.id ?? null);
    const [selected, setSelected] = useState<number[]>([]);
    const [withCombo, setWithCombo] = useState(preselectCombo && comboAvailable);
    const [comboChoice, setComboChoice] = useState<string | null>(combo?.choices[0] ?? null);
    const [quantity, setQuantity] = useState(1);
    const [notes, setNotes] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const unitPrice = useMemo(() => {
        const base = variants.find((variant) => variant.id === variantId)?.price ?? product.price;
        const extras = groups
            .flatMap((group) => group.modifiers ?? [])
            .filter((modifier) => selected.includes(modifier.id))
            .reduce((sum, modifier) => sum + modifier.price, 0);

        return base + extras + (withCombo && combo ? combo.price : 0);
    }, [variants, variantId, product.price, groups, selected, withCombo, combo]);

    const toggle = (group: ModifierGroup, modifierId: number) => {
        const groupIds = (group.modifiers ?? []).map((modifier) => modifier.id);
        const chosenInGroup = selected.filter((id) => groupIds.includes(id));

        if (selected.includes(modifierId)) {
            setSelected(selected.filter((id) => id !== modifierId));
        } else if (group.max_select === 1) {
            setSelected([...selected.filter((id) => !groupIds.includes(id)), modifierId]);
        } else if (chosenInGroup.length < group.max_select) {
            setSelected([...selected, modifierId]);
        }
    };

    const missing = groups.find((group) => {
        const groupIds = (group.modifiers ?? []).map((modifier) => modifier.id);

        return selected.filter((id) => groupIds.includes(id)).length < group.min_select;
    });

    const submit = () => {
        setSubmitting(true);
        cartActions.add(
            {
                product_id: product.id,
                variant_id: variantId,
                modifier_ids: selected,
                combo: withCombo,
                combo_choice: withCombo ? comboChoice : null,
                quantity,
                notes: notes.trim() || null,
            },
            onClose,
        );
    };

    const option = (active: boolean) =>
        cn(
            'flex min-h-11 w-full flex-wrap items-center justify-between gap-x-2 rounded-lg border px-3 py-2 text-left text-sm leading-tight transition-colors',
            active ? 'border-brand-500 bg-brand-900 text-white' : 'border-ink-600 bg-ink-900 text-ink-200 hover:border-ink-400',
        );

    return (
        <Modal
            open
            onClose={onClose}
            title={
                <span>
                    {product.code && <span className="mr-2 rounded bg-flame-400 px-1.5 py-0.5 text-xs font-extrabold text-ink-950">{product.code}</span>}
                    {product.name}
                </span>
            }
            footer={
                <div className="flex w-full items-center gap-3">
                    <div className="flex items-center rounded-lg border border-ink-600">
                        <button type="button" className="p-2.5 text-ink-300 hover:text-white disabled:opacity-40" onClick={() => setQuantity(Math.max(1, quantity - 1))} disabled={quantity <= 1} aria-label="Decrease quantity">
                            <Minus className="size-4" />
                        </button>
                        <span className="w-8 text-center text-sm font-bold" aria-live="polite">
                            {quantity}
                        </span>
                        <button type="button" className="p-2.5 text-ink-300 hover:text-white disabled:opacity-40" onClick={() => setQuantity(Math.min(20, quantity + 1))} disabled={quantity >= 20} aria-label="Increase quantity">
                            <Plus className="size-4" />
                        </button>
                    </div>
                    <Button size="lg" className="flex-1" onClick={submit} disabled={missing !== undefined} loading={submitting}>
                        {missing ? `Choose ${missing.name}` : `Add to ticket · ${money(unitPrice * quantity)}`}
                    </Button>
                </div>
            }
        >
            <div className="space-y-5">
                {product.description && <p className="text-sm text-ink-400">{product.description}</p>}

                {variants.length > 0 && (
                    <fieldset>
                        <legend className="mb-2 text-xs font-extrabold tracking-wider text-ink-300 uppercase">Choose size</legend>
                        <div className="grid grid-cols-2 gap-2">
                            {variants.map((variant) => (
                                <button key={variant.id} type="button" className={option(variantId === variant.id)} onClick={() => setVariantId(variant.id)} aria-pressed={variantId === variant.id}>
                                    <span className="font-semibold">{variant.name}</span>
                                    <span className="font-bold text-flame-400">{money(variant.price)}</span>
                                </button>
                            ))}
                        </div>
                    </fieldset>
                )}

                {groups.map((group) => (
                    <fieldset key={group.id}>
                        <legend className="mb-2 flex w-full items-center justify-between text-xs font-extrabold tracking-wider text-ink-300 uppercase">
                            {group.name}
                            <span className="font-semibold tracking-normal text-ink-500 normal-case">
                                {group.min_select > 0 ? 'Required' : 'Optional'}
                                {group.max_select > 1 && ` · up to ${group.max_select}`}
                            </span>
                        </legend>
                        <div className="grid grid-cols-2 gap-2">
                            {(group.modifiers ?? [])
                                .filter((modifier) => modifier.is_available)
                                .map((modifier) => (
                                    <button
                                        key={modifier.id}
                                        type="button"
                                        className={option(selected.includes(modifier.id))}
                                        onClick={() => toggle(group, modifier.id)}
                                        aria-pressed={selected.includes(modifier.id)}
                                    >
                                        <span>{modifier.name}</span>
                                        {modifier.price > 0 && <span className="text-xs font-bold text-flame-400">+{money(modifier.price)}</span>}
                                    </button>
                                ))}
                        </div>
                    </fieldset>
                ))}

                {comboAvailable && combo && (
                    <fieldset className="rounded-xl border border-flame-500/40 bg-flame-900/40 p-3">
                        <legend className="sr-only">Combo upgrade</legend>
                        <label className="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" className="mt-1 size-4 accent-flame-400" checked={withCombo} onChange={(event) => setWithCombo(event.target.checked)} />
                            <span className="min-w-0 flex-1">
                                <span className="flex items-center justify-between gap-2 text-sm font-bold text-white">
                                    Upgrade to {combo.name}
                                    <span className="text-flame-400">+{money(combo.price)}</span>
                                </span>
                                <span className="block text-xs text-ink-300">{combo.description}</span>
                            </span>
                        </label>
                        {withCombo && combo.choices.length > 0 && (
                            <div className="mt-3 flex flex-wrap gap-2 pl-7">
                                {combo.choices.map((choice) => (
                                    <button
                                        key={choice}
                                        type="button"
                                        onClick={() => setComboChoice(choice)}
                                        aria-pressed={comboChoice === choice}
                                        className={cn(
                                            'rounded-lg border px-3 py-1.5 text-xs font-semibold',
                                            comboChoice === choice ? 'border-flame-400 bg-flame-400 text-ink-950' : 'border-ink-600 text-ink-200 hover:border-ink-400',
                                        )}
                                    >
                                        {choice}
                                    </button>
                                ))}
                            </div>
                        )}
                    </fieldset>
                )}

                <label className="block">
                    <span className="mb-2 block text-xs font-extrabold tracking-wider text-ink-300 uppercase">Notes for the kitchen</span>
                    <input
                        type="text"
                        maxLength={200}
                        value={notes}
                        onChange={(event) => setNotes(event.target.value)}
                        placeholder="e.g. Extra Boss Sauce, cut in half"
                        className="h-10 w-full rounded-lg border border-ink-600 bg-ink-900 px-3 text-sm text-ink-100 placeholder:text-ink-500 focus:border-flame-400 focus:outline-none"
                    />
                </label>
            </div>
        </Modal>
    );
}
