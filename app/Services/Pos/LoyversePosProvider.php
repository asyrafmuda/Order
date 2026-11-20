<?php

namespace App\Services\Pos;

use App\Contracts\PosProvider;
use App\Data\PosCatalogItem;
use App\Data\PosReceipt;
use App\Exceptions\PosException;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Loyverse POS REST API (https://developer.loyverse.com/docs/).
 *
 * Amounts are sent in ringgit as decimals; this application stores sen.
 * Each order line needs a Loyverse variant id, which is stored on the local
 * product / variant by the "pos:sync-menu" command.
 */
class LoyversePosProvider implements PosProvider
{
    /**
     * @param  array{base_url: string, access_token: ?string, store_id: ?string, payment_type_id: ?string, webhook_secret: ?string, timeout: int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'loyverse';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['access_token']) && filled($this->config['store_id']);
    }

    public function testConnection(): string
    {
        $merchant = $this->send('get', '/merchant');

        return (string) ($merchant->json('business_name') ?? 'Loyverse account connected');
    }

    public function pushOrder(Order $order): PosReceipt
    {
        $order->loadMissing(['items.product', 'items.modifiers', 'items.product.variants']);

        $payload = $this->receiptPayload($order);
        $response = $this->send('post', '/receipts', $payload);
        $receiptNumber = $response->json('receipt_number');

        if (! $receiptNumber) {
            throw new PosException('Loyverse did not return a receipt number.');
        }

        return new PosReceipt((string) $receiptNumber, $payload, (array) $response->json());
    }

    public function catalog(): array
    {
        $items = [];
        $cursor = null;

        do {
            $response = $this->send('get', '/items', array_filter(['limit' => 250, 'cursor' => $cursor]));

            foreach ($response->json('items', []) as $item) {
                foreach ($item['variants'] ?? [] as $variant) {
                    $items[] = new PosCatalogItem(
                        itemId: (string) $item['id'],
                        variantId: (string) $variant['variant_id'],
                        itemName: (string) $item['item_name'],
                        variantName: $variant['option1_value'] ?? null,
                        sku: $variant['sku'] ?? null,
                        price: isset($variant['default_price']) ? (int) round($variant['default_price'] * 100) : null,
                    );
                }
            }

            $cursor = $response->json('cursor');
        } while ($cursor);

        return $items;
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = $this->config['webhook_secret'];

        if (blank($secret)) {
            return false;
        }

        $signature = (string) $request->header('X-Loyverse-Signature');
        $digest = hash_hmac('sha256', $request->getContent(), $secret, true);

        return hash_equals(base64_encode($digest), $signature) || hash_equals(bin2hex($digest), $signature);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PosException
     */
    public function receiptPayload(Order $order): array
    {
        $lineItems = $order->items->map(function (OrderItem $item) {
            $variantId = $item->product_variant_id
                ? $item->product?->variants->firstWhere('id', $item->product_variant_id)?->pos_variant_id
                : $item->product?->pos_variant_id;

            if (! $variantId) {
                throw new PosException("\"{$item->name}\" is not linked to a Loyverse item. Run the menu sync first.");
            }

            $notes = array_filter([
                $item->modifiers->pluck('name')->implode(', '),
                $item->combo_name ? trim("{$item->combo_name}: {$item->combo_choice}", ': ') : null,
                $item->notes,
            ]);

            return array_filter([
                'variant_id' => $variantId,
                'quantity' => $item->quantity,
                'price' => round($item->unit_price / 100, 2),
                'line_note' => $notes ? implode(' | ', $notes) : null,
            ], fn ($value) => $value !== null);
        })->all();

        $itemsTotal = (int) $order->items->sum('line_total');

        return array_filter([
            'store_id' => $this->config['store_id'],
            'order' => $order->order_number,
            'source' => config('app.name'),
            'receipt_date' => ($order->confirmed_at ?? now())->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'note' => trim(sprintf(
                '%s %s — %s%s%s',
                $order->type->label(),
                $order->order_number,
                $order->customer_name,
                $order->customer_phone ? " ({$order->customer_phone})" : '',
                $order->notes ? ' — '.$order->notes : '',
            )),
            'line_items' => $lineItems,
            'payments' => $this->config['payment_type_id'] ? [[
                'payment_type_id' => $this->config['payment_type_id'],
                'money_amount' => round($itemsTotal / 100, 2),
            ]] : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PosException
     */
    private function send(string $method, string $path, array $data = []): Response
    {
        if (! $this->isConfigured()) {
            throw new PosException('Loyverse is not configured. Set LOYVERSE_ACCESS_TOKEN and LOYVERSE_STORE_ID.');
        }

        try {
            $response = $this->client()->{$method}($path, $data);
        } catch (ConnectionException $exception) {
            throw new PosException('Could not reach Loyverse: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->failed()) {
            $details = $response->json('errors.0.details') ?? $response->json('errors.0.code') ?? $response->body();

            throw new PosException("Loyverse responded with HTTP {$response->status()}: {$details}");
        }

        return $response;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->config['base_url'])
            ->withToken((string) $this->config['access_token'])
            ->acceptJson()
            ->asJson()
            ->timeout($this->config['timeout']);
    }
}
