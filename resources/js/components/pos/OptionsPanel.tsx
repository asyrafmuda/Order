import { Check, Minus, Plus, X } from 'lucide-react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { money } from '@/lib/format';
import { unitPrice, type PosLine, type PosLineInput } from '@/lib/pos';
import type { ComboOffer, ModifierGroup, Product } from '@/types';

interface OptionsPanelProps {
    product: Product;
    combo: ComboOffer | null;
    quickNotes: string[];
    /** The ticket line being edited, or null when adding a new item. */
    editing: PosLine | null;
    onSubmit: (line: PosLineInput) => void;
    onClose: () => void;
}

/**
 * Everything about one item on a single screen: size, things to leave out,
 * extras, combo, kitchen notes and quantity. No steps, no scrolling through
 * dialogs — tap the chips, press Enter.
 */
export function OptionsPanel({ product, combo, quickNotes, editing, onSubmit, onClose }: OptionsPanelProps) {
    const variants = product.variants ?? [];
    const groups = (product.modifier_groups ?? []).filter((group) => (group.modifiers ?? []).some((modifier) => modifier.is_available));
    const comboAvailable = combo !== null && product.is_combo_eligible;

    // When editing, split the saved note back into its quick-note chips and the typed remainder.
    const savedNotes = (editing?.notes ?? '').split(',').map((note) => note.trim()).filter(Boolean);

    const [variantId, setVariantId] = useState<number | null>(editing?.variantId ?? (variants.find((variant) => variant.is_default) ?? variants[0])?.id ?? null);
    const [modifierIds, setModifierIds] = useState<number[]>(editing?.modifierIds ?? []);
    const [comboChoice, setComboChoice] = useState<string | null>(editing?.combo ? (editing.comboChoice ?? combo?.choices[0] ?? '') : null);
    const [quick, setQuick] = useState<string[]>(savedNotes.filter((note) => quickNotes.includes(note)));
    const [custom, setCustom] = useState(savedNotes.filter((note) => !quickNotes.includes(note)).join(', '));
    const [quantity, setQuantity] = useState(editing?.quantity ?? 1);

    const line: PosLineInput = useMemo(
        () => ({
            product,
            variantId,
            modifierIds,
            combo: comboChoice !== null,
            comboChoice: comboChoice || null,
            notes: [...quick, custom.trim()].filter(Boolean).join(', '),
            quantity,
        }),
        [product, variantId, modifierIds, comboChoice, quick, custom, quantity],
    );

    const missing = groups.find((group) => {
        const ids = (group.modifiers ?? []).map((modifier) => modifier.id);

        return modifierIds.filter((id) => ids.includes(id)).length < group.min_select;
    });

    const submit = () => {
        if (!missing) onSubmit(line);
    };

    // Enter adds, Escape backs out — the cashier never has to reach for the mouse.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();
            } else if (event.key === 'Enter') {
                // Always "add", even when a chip has focus (Space still toggles a focused chip).
                event.preventDefault();
                submit();
            }
        };
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    });

    const toggleModifier = (group: ModifierGroup, id: number) => {
        const ids = (group.modifiers ?? []).map((modifier) => modifier.id);
        const chosen = modifierIds.filter((item) => ids.includes(item));

        if (modifierIds.includes(id)) {
            setModifierIds(modifierIds.filter((item) => item !== id));
        } else if (group.max_select === 1) {
            setModifierIds([...modifierIds.filter((item) => !ids.includes(item)), id]);
        } else if (chosen.length < group.max_select) {
            setModifierIds([...modifierIds, id]);
        }
    };

    return (
        <section className="absolute inset-0 z-20 flex animate-slide-up flex-col bg-ink-900" aria-label={`Options for ${product.name}`}>
            <header className="flex items-center gap-3 border-b border-ink-700 px-4 py-3">
                <div className="min-w-0 flex-1">
                    <p className="text-[11px] font-extrabold tracking-[0.15em] text-flame-400 uppercase">{editing ? 'Edit item' : 'Add item'}</p>
                    <h2 className="truncate font-display text-3xl leading-none text-white uppercase">
                        {product.code && <span className="mr-2 text-ink-400">{product.code}</span>}
                        {product.name}
                    </h2>
                </div>
                <button type="button" onClick={onClose} className="flex h-11 items-center gap-1.5 rounded-lg bg-ink-700 px-3 text-sm font-bold text-ink-100 hover:bg-ink-600" aria-label="Close options">
                    <X className="size-4" /> Esc
                </button>
            </header>

            <div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-4">
                {variants.length > 0 && (
                    <Group title="Size" hint="Required">
                        {variants.map((variant) => (
                            <Chip key={variant.id} active={variantId === variant.id} onClick={() => setVariantId(variant.id)} suffix={money(variant.price)} large>
                                {variant.name}
                            </Chip>
                        ))}
                    </Group>
                )}

                {groups.map((group) => (
                    <Group
                        key={group.id}
                        title={group.name}
                        hint={`${group.min_select > 0 ? 'Required' : 'Optional'}${group.max_select > 1 ? ` · up to ${group.max_select}` : ''}`}
                        attention={missing?.id === group.id}
                    >
                        {(group.modifiers ?? [])
                            .filter((modifier) => modifier.is_available)
                            .map((modifier) => (
                                <Chip
                                    key={modifier.id}
                                    active={modifierIds.includes(modifier.id)}
                                    onClick={() => toggleModifier(group, modifier.id)}
                                    suffix={modifier.price > 0 ? `+${money(modifier.price, false)}` : undefined}
                                >
                                    {modifier.name}
                                </Chip>
                            ))}
                    </Group>
                ))}

                {comboAvailable && combo && (
                    <Group title={combo.name} hint={`${combo.description ?? 'Upgrade'} · +${money(combo.price)}`}>
                        <Chip active={comboChoice === null} onClick={() => setComboChoice(null)}>
                            No combo
                        </Chip>
                        {(combo.choices.length > 0 ? combo.choices : ['']).map((choice) => (
                            <Chip key={choice} active={comboChoice === choice} onClick={() => setComboChoice(choice)} suffix={`+${money(combo.price, false)}`}>
                                {choice || combo.name}
                            </Chip>
                        ))}
                    </Group>
                )}

                <Group title="Kitchen notes" hint="Optional">
                    {quickNotes.map((note) => (
                        <Chip key={note} active={quick.includes(note)} onClick={() => setQuick(quick.includes(note) ? quick.filter((item) => item !== note) : [...quick, note])}>
                            {note}
                        </Chip>
                    ))}
                    <input
                        type="text"
                        value={custom}
                        maxLength={120}
                        onChange={(event) => setCustom(event.target.value)}
                        placeholder="Type anything else for the kitchen…"
                        aria-label="Other kitchen note"
                        className="h-12 min-w-56 flex-1 rounded-xl border border-ink-600 bg-ink-850 px-3 text-sm text-ink-100 placeholder:text-ink-500 focus:border-flame-400 focus:outline-none"
                    />
                </Group>
            </div>

            <footer className="flex items-center gap-3 border-t border-ink-700 px-4 py-3">
                <div className="flex items-center rounded-xl border border-ink-600 bg-ink-850">
                    <button type="button" className="flex size-14 items-center justify-center text-ink-200 hover:text-white disabled:opacity-30" disabled={quantity <= 1} onClick={() => setQuantity(quantity - 1)} aria-label="Decrease quantity">
                        <Minus className="size-5" />
                    </button>
                    <span className="w-10 text-center font-display text-3xl leading-none text-white" aria-live="polite">
                        {quantity}
                    </span>
                    <button type="button" className="flex size-14 items-center justify-center text-ink-200 hover:text-white disabled:opacity-30" disabled={quantity >= 50} onClick={() => setQuantity(quantity + 1)} aria-label="Increase quantity">
                        <Plus className="size-5" />
                    </button>
                </div>
                <button
                    type="button"
                    onClick={submit}
                    disabled={missing !== undefined}
                    className="flex h-14 flex-1 items-center justify-center gap-2 rounded-xl bg-go-500 px-4 text-base font-extrabold text-ink-950 transition-colors hover:bg-go-400 disabled:cursor-not-allowed disabled:bg-ink-700 disabled:text-ink-400"
                >
                    {missing ? (
                        `Choose ${missing.name}`
                    ) : (
                        <>
                            <Check className="size-5" aria-hidden />
                            {editing ? 'Update item' : 'Add to order'} · {money(unitPrice(line, combo) * quantity)}
                            <kbd className="ml-1 hidden rounded bg-ink-950/20 px-1.5 py-0.5 text-[11px] font-bold sm:inline">Enter</kbd>
                        </>
                    )}
                </button>
            </footer>
        </section>
    );
}

function Group({ title, hint, attention, children }: { title: string; hint?: string; attention?: boolean; children: ReactNode }) {
    return (
        <fieldset>
            <legend className="mb-2 flex w-full items-baseline gap-2">
                <span className={cn('text-xs font-extrabold tracking-[0.12em] uppercase', attention ? 'text-brand-400' : 'text-ink-200')}>{title}</span>
                {hint && <span className={cn('text-[11px]', attention ? 'font-bold text-brand-400' : 'text-ink-500')}>{hint}</span>}
            </legend>
            <div className="flex flex-wrap gap-2">{children}</div>
        </fieldset>
    );
}

function Chip({ active, onClick, suffix, large, children }: { active: boolean; onClick: () => void; suffix?: string; large?: boolean; children: ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'flex items-center gap-2 rounded-xl border px-4 text-sm font-bold transition-colors duration-100 active:scale-[0.97]',
                large ? 'h-14 min-w-36 justify-between' : 'h-12',
                active ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-600 bg-ink-850 text-ink-100 hover:border-ink-400',
            )}
        >
            {active && !large && <Check className="size-4 shrink-0" aria-hidden />}
            {children}
            {suffix && <span className={cn('text-xs font-extrabold', active ? 'text-white/90' : 'text-flame-400')}>{suffix}</span>}
        </button>
    );
}
