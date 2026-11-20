<?php

namespace App\Services\Cart;

use App\Enums\OrderType;
use App\Exceptions\InvalidCartLine;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\Product;
use App\Support\OutletContext;
use App\Support\OutletPrices;
use App\Support\OutletStock;
use App\Support\Settings;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * The customer's session cart ("Current Ticket"). Only ids, quantities and
 * notes are stored; prices are always recalculated by CartPricer.
 */
class Cart
{
    private const LINES = 'cart.lines';

    private const TYPE = 'cart.type';

    private const ZONE = 'cart.zone_id';

    private const TABLE = 'cart.table_id';

    private const COUNTER = 'cart.at_counter';

    /** How long a scan of the shop QR code counts as "standing at the counter". */
    private const COUNTER_HOURS = 3;

    private ?PricedCart $priced = null;

    private ?DiningTable $table = null;

    public function __construct(
        private readonly Session $session,
        private readonly CartPricer $pricer,
        private readonly Settings $settings,
        private readonly OutletContext $outlets,
        private readonly OutletStock $stock,
        private readonly OutletPrices $prices,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lines(): array
    {
        return array_values($this->session->get(self::LINES, []));
    }

    /**
     * Add an item, merging with an identical line when one exists.
     *
     * @param  array{product_id: int, variant_id?: int|null, modifier_ids?: array<int, int>, combo?: bool, combo_choice?: string|null, quantity?: int, notes?: string|null}  $input
     *
     * @throws InvalidCartLine
     */
    public function add(array $input): PricedLine
    {
        $modifierIds = array_values(array_unique(array_map('intval', $input['modifier_ids'] ?? [])));
        sort($modifierIds);

        $line = [
            'id' => (string) Str::ulid(),
            'product_id' => (int) $input['product_id'],
            'variant_id' => isset($input['variant_id']) ? (int) $input['variant_id'] : null,
            'modifier_ids' => $modifierIds,
            'combo' => (bool) ($input['combo'] ?? false),
            'combo_choice' => $input['combo_choice'] ?? null,
            'quantity' => max(1, (int) ($input['quantity'] ?? 1)),
            'notes' => filled($input['notes'] ?? null) ? trim((string) $input['notes']) : null,
        ];

        $priced = $this->pricer->resolveLine($line);
        $line['combo_choice'] = $priced->comboChoice;

        $lines = $this->lines();
        $signature = $this->signature($line);

        foreach ($lines as $index => $existing) {
            if ($this->signature($existing) === $signature) {
                $lines[$index]['quantity'] = min(99, $existing['quantity'] + $line['quantity']);
                $this->store($lines);

                return $priced;
            }
        }

        $lines[] = $line;
        $this->store($lines);

        return $priced;
    }

    public function setQuantity(string $lineId, int $quantity): void
    {
        if ($quantity < 1) {
            $this->remove($lineId);

            return;
        }

        $this->store(array_map(
            fn (array $line) => $line['id'] === $lineId ? [...$line, 'quantity' => min(99, $quantity)] : $line,
            $this->lines(),
        ));
    }

    /**
     * Turn an existing line into a combo (the hot deal banner).
     */
    public function upgradeToCombo(string $lineId, ?string $choice = null): void
    {
        $this->store(array_map(
            fn (array $line) => $line['id'] === $lineId ? [...$line, 'combo' => true, 'combo_choice' => $choice] : $line,
            $this->lines(),
        ));
    }

    public function remove(string $lineId): void
    {
        $this->store(array_filter($this->lines(), fn (array $line) => $line['id'] !== $lineId));
    }

    public function clear(): void
    {
        $this->session->forget(self::LINES);
        $this->priced = null;
    }

    public function isEmpty(): bool
    {
        return $this->lines() === [];
    }

    /**
     * The table the customer is sitting at, set by scanning its QR code.
     */
    public function table(): ?DiningTable
    {
        $tableId = $this->session->get(self::TABLE);

        if (! $tableId || ! $this->settings->bool('ordering.dine_in_enabled')) {
            return null;
        }

        // The table must be in the outlet being ordered from; picking another outlet leaves it.
        return $this->table ??= DiningTable::query()->active()->forOutlet($this->outlets->current())->find($tableId);
    }

    public function sitAt(DiningTable $table): void
    {
        $this->leaveCounter();
        $this->session->put(self::TABLE, $table->id);
        $this->table = $table;
        $this->priced = null;
    }

    public function leaveTable(): void
    {
        $this->session->forget(self::TABLE);
        $this->table = null;
        $this->priced = null;
    }

    /**
     * Whether the customer scanned the shop QR code and is ordering at the
     * counter: they are standing there, so the order is a pickup that needs
     * no name or number and can be paid on the spot. A scan stops counting
     * after a few hours, and when another outlet is chosen.
     */
    public function atCounter(): bool
    {
        $scan = $this->session->get(self::COUNTER);

        return is_array($scan)
            && ! $this->table()
            && (int) ($scan['outlet_id'] ?? 0) === $this->outlets->current()->id
            && (int) ($scan['at'] ?? 0) > now()->subHours(self::COUNTER_HOURS)->getTimestamp()
            && $this->settings->bool('ordering.pickup_enabled');
    }

    public function arriveAtCounter(Outlet $outlet): void
    {
        $this->leaveTable();
        $this->session->put(self::COUNTER, ['outlet_id' => $outlet->id, 'at' => now()->getTimestamp()]);
        $this->session->put(self::TYPE, OrderType::Pickup->value);
        $this->priced = null;
    }

    public function leaveCounter(): void
    {
        $this->session->forget(self::COUNTER);
    }

    public function type(): OrderType
    {
        // Sitting at a table always means dine-in; leave the table to change it.
        if ($this->table()) {
            return OrderType::DineIn;
        }

        if ($this->atCounter()) {
            return OrderType::Pickup;
        }

        $type = OrderType::tryFrom((string) $this->session->get(self::TYPE)) ?? OrderType::Pickup;

        if ($type === OrderType::DineIn) {
            $type = OrderType::Pickup;
        }

        if ($type === OrderType::Delivery && ! $this->settings->bool('ordering.delivery_enabled')) {
            return OrderType::Pickup;
        }

        if ($type === OrderType::Pickup && ! $this->settings->bool('ordering.pickup_enabled')) {
            return OrderType::Delivery;
        }

        return $type;
    }

    public function setType(OrderType $type): void
    {
        // Choosing pickup or delivery means the customer is not ordering for the
        // table, nor standing at the counter.
        $this->leaveTable();
        $this->leaveCounter();
        $this->session->put(self::TYPE, $type->value);
        $this->priced = null;
    }

    public function zone(): ?DeliveryZone
    {
        $zoneId = $this->session->get(self::ZONE);

        return DeliveryZone::query()->active()
            ->forOutlet($this->outlets->current())
            ->when($zoneId, fn ($query) => $query->orderByRaw('id = ? desc', [$zoneId]))
            ->orderBy('sort_order')
            ->first();
    }

    public function setZone(?int $zoneId): void
    {
        $this->session->put(self::ZONE, $zoneId);
        $this->priced = null;
    }

    /**
     * The cart priced against the live menu. Lines that can no longer be sold
     * are removed from the session.
     */
    public function priced(): PricedCart
    {
        if ($this->priced) {
            return $this->priced;
        }

        $invalid = [];
        $type = $this->type();
        $priced = $this->pricer->price($this->lines(), $type, $type === OrderType::Delivery ? $this->zone() : null, $invalid);

        if ($invalid !== []) {
            $this->store(array_filter($this->lines(), fn (array $line) => ! in_array($line['id'], $invalid, true)));
        }

        return $this->priced = $priced;
    }

    /**
     * The cart as sent to the browser, including a couple of add-on suggestions.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $priced = $this->priced();
        $inCart = $priced->lines->map(fn (PricedLine $line) => $line->product->id)->all();

        $upsells = $priced->isEmpty() ? collect() : Product::query()
            ->where('is_upsell', true)
            ->where('status', 'available')
            ->whereNotIn('id', [...$inCart, ...$this->stock->soldOutIds()])
            ->whereDoesntHave('variants')
            ->ordered()
            ->limit(2)
            ->get()
            ->tap(fn ($products) => $this->prices->apply($products))
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
            ]);

        $table = $this->table();

        return [
            ...$priced->toArray(),
            'table' => $table ? ['id' => $table->id, 'name' => $table->name] : null,
            'at_counter' => $this->atCounter(),
            'upsells' => $upsells->values()->all(),
        ];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $lines
     */
    private function store(array $lines): void
    {
        $this->session->put(self::LINES, array_values($lines));
        $this->priced = null;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function signature(array $line): string
    {
        return md5(json_encode([
            $line['product_id'],
            $line['variant_id'],
            $line['modifier_ids'],
            $line['combo'],
            $line['combo_choice'],
            $line['notes'],
        ]));
    }
}
