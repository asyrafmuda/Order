<?php

namespace App\Http\Controllers\Webhooks;

use App\Contracts\PosProvider;
use App\Http\Controllers\Controller;
use App\Jobs\SyncPosMenuJob;
use App\Models\PosSyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Loyverse webhooks. Catalogue changes re-link the menu so new
 * items can be sold without a manual sync.
 */
class LoyverseWebhookController extends Controller
{
    public function __invoke(Request $request, PosProvider $pos): JsonResponse
    {
        abort_unless($pos->verifyWebhook($request), 403, 'Invalid webhook signature.');

        $type = (string) $request->input('type', 'unknown');

        PosSyncLog::query()->create([
            'provider' => $pos->name(),
            'action' => 'webhook:'.$type,
            'status' => 'received',
            'request' => ['type' => $type, 'merchant_id' => $request->input('merchant_id')],
        ]);

        if (str_starts_with($type, 'items.')) {
            SyncPosMenuJob::dispatch();
        }

        return response()->json(['received' => true]);
    }
}
