<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DiningTableRequest;
use App\Models\DiningTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiningTableController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Tables/Index', [
            'tables' => $this->tables(),
        ]);
    }

    /**
     * A print-ready sheet of QR codes, one card per active table.
     */
    public function print(): Response
    {
        return Inertia::render('Admin/Tables/Print', [
            'tables' => array_values(array_filter($this->tables(), fn (array $table) => $table['is_active'])),
        ]);
    }

    public function store(DiningTableRequest $request): RedirectResponse
    {
        $table = DiningTable::query()->create($request->validated());

        return back()->with('success', "{$table->name} added. Print its QR code and place it on the table.");
    }

    public function update(DiningTableRequest $request, DiningTable $table): RedirectResponse
    {
        $table->update($request->validated());

        return back()->with('success', "{$table->name} updated.");
    }

    /**
     * Issue a new QR code, e.g. when a printed one was photographed and is
     * being misused. The old code stops working immediately.
     */
    public function regenerate(DiningTable $table): RedirectResponse
    {
        $table->update(['token' => DiningTable::newToken()]);

        return back()->with('success', "New QR code issued for {$table->name}. Reprint it — the old one no longer works.");
    }

    public function destroy(Request $request, DiningTable $table): RedirectResponse
    {
        $open = $table->orders()->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])->exists();

        if ($open) {
            return back()->with('error', "{$table->name} still has an open order.");
        }

        // Past orders keep the table's name in orders.table_label.
        $table->delete();

        return back()->with('success', "{$table->name} removed.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tables(): array
    {
        return DiningTable::query()
            ->forOutlet($this->outlet())
            ->ordered()
            ->withCount(['orders as open_orders_count' => fn ($query) => $query->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])])
            ->get()
            ->map(fn (DiningTable $table) => [
                'id' => $table->id,
                'name' => $table->name,
                'seats' => $table->seats,
                'sort_order' => $table->sort_order,
                'is_active' => $table->is_active,
                'open_orders' => $table->open_orders_count,
                'url' => route('tables.show', $table->token),
            ])
            ->all();
    }
}
