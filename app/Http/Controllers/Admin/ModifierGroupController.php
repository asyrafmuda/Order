<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModifierGroupRequest;
use App\Http\Resources\ModifierGroupResource;
use App\Models\ModifierGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ModifierGroupController extends Controller
{
    public function index(Request $request): Response
    {
        $groups = ModifierGroup::query()->with('modifiers')->withCount('products')->orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('Admin/ModifierGroups/Index', [
            'groups' => ModifierGroupResource::collection($groups)->resolve($request),
        ]);
    }

    public function store(ModifierGroupRequest $request): RedirectResponse
    {
        $group = DB::transaction(function () use ($request) {
            $group = ModifierGroup::query()->create($request->safe()->except('modifiers'));
            $this->syncModifiers($group, $request->validated('modifiers'));

            return $group;
        });

        return back()->with('success', "{$group->name} was created.");
    }

    public function update(ModifierGroupRequest $request, ModifierGroup $modifierGroup): RedirectResponse
    {
        DB::transaction(function () use ($request, $modifierGroup) {
            $modifierGroup->update($request->safe()->except('modifiers'));
            $this->syncModifiers($modifierGroup, $request->validated('modifiers'));
        });

        return back()->with('success', "{$modifierGroup->name} was updated.");
    }

    public function destroy(ModifierGroup $modifierGroup): RedirectResponse
    {
        // Detaches from products and removes its options (cascade); past orders keep their own copy.
        $modifierGroup->delete();

        return back()->with('success', "{$modifierGroup->name} was deleted.");
    }

    /**
     * Replace the group's options with the submitted list, keeping the id
     * (and POS link) of rows that were only edited.
     *
     * @param  array<int, array{id?: int|null, name: string, price: int, is_available: bool}>  $modifiers
     */
    private function syncModifiers(ModifierGroup $group, array $modifiers): void
    {
        $existing = $group->modifiers()->get()->keyBy('id');
        $kept = [];

        foreach (array_values($modifiers) as $index => $modifier) {
            $attributes = [
                'name' => $modifier['name'],
                'price' => (int) $modifier['price'],
                'is_available' => (bool) $modifier['is_available'],
                'sort_order' => $index,
            ];

            $model = isset($modifier['id']) ? $existing->get((int) $modifier['id']) : null;

            if ($model) {
                $model->update($attributes);
            } else {
                $model = $group->modifiers()->create($attributes);
            }

            $kept[] = $model->id;
        }

        $group->modifiers()->whereNotIn('id', $kept)->delete();
    }
}
