<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Pos\SyncPosMenu;
use App\Contracts\MessageChannel;
use App\Contracts\PaymentGateway;
use App\Contracts\PosProvider;
use App\Enums\SyncStatus;
use App\Exceptions\PosException;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\PosSyncLog;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(PosProvider $pos, PaymentGateway $gateway, MessageChannel $channel): Response
    {
        $products = Product::query()->count();
        $linked = Product::query()->whereNotNull('pos_item_id')->count();

        return Inertia::render('Admin/Integrations/Index', [
            // Not "pos": that name is the shared prop the page header reads.
            'posStatus' => [
                'provider' => $pos->name(),
                'configured' => $pos->isConfigured(),
                'products_linked' => $linked,
                'products_unlinked' => $products - $linked,
                'orders_failed' => Order::query()->forOutlet($this->outlet())->where('pos_sync_status', SyncStatus::Failed)->count(),
                'webhook_url' => route('webhooks.loyverse'),
            ],
            'payment' => ['gateway' => $gateway->name(), 'callback_url' => route('payments.callback')],
            'messaging' => ['channel' => $channel->name()],
            'posLogs' => PosSyncLog::query()->with('order')->latest('id')->limit(30)->get()->map(fn (PosSyncLog $log) => [
                'id' => $log->id,
                'provider' => $log->provider,
                'action' => $log->action,
                'status' => $log->status,
                'error' => $log->error,
                'order_number' => $log->order?->order_number,
                'order_uuid' => $log->order?->uuid,
                'at' => $log->created_at?->toIso8601String(),
            ]),
            'notificationLogs' => NotificationLog::query()->with('order')->latest('id')->limit(30)->get()->map(fn (NotificationLog $log) => [
                'id' => $log->id,
                'channel' => $log->channel,
                'recipient' => $log->recipient,
                'template' => $log->template,
                'message' => $log->message,
                'status' => $log->status,
                'error' => $log->error,
                'order_number' => $log->order?->order_number,
                'order_uuid' => $log->order?->uuid,
                'at' => $log->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function testPos(PosProvider $pos): RedirectResponse
    {
        try {
            $account = $pos->testConnection();
        } catch (PosException $exception) {
            return back()->with('error', 'POS connection failed: '.$exception->getMessage());
        }

        return back()->with('success', "POS connection OK — {$account}.");
    }

    public function syncMenu(SyncPosMenu $sync): RedirectResponse
    {
        try {
            $result = $sync->handle();
        } catch (PosException $exception) {
            return back()->with('error', 'Menu sync failed: '.$exception->getMessage());
        }

        $unmatched = count($result['unmatched']);

        return back()->with(
            $unmatched > 0 && $result['linked'] === 0 ? 'error' : 'success',
            "Linked {$result['linked']} menu item(s) to the POS."
                .($unmatched > 0 ? " {$unmatched} could not be matched — check the SKU or name in the POS." : ''),
        );
    }
}
