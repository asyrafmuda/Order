<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
        ]);

        $users = User::query()
            ->withCount('orders')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $term = '%'.trim($search).'%';

                $query->where(fn ($query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'is_active' => $user->is_active,
                'orders_count' => $user->orders_count,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
            ],
            'roles' => UserRole::options(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::query()->create($this->attributes($request));
            $this->ensureRiderProfile($user);

            return $user;
        });

        return back()->with('success', "{$user->name} was added as {$user->role->label()}.");
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $user->update($this->attributes($request));
            $this->ensureRiderProfile($user);
        });

        return back()->with('success', "{$user->name} was updated.");
    }

    /**
     * Accounts with history (orders, deliveries) are deactivated so records
     * keep pointing at a person; unused accounts are deleted.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $hasHistory = $user->orders()->exists() || ($user->rider && $user->rider->deliveries()->exists());

        if ($hasHistory) {
            $user->update(['is_active' => false]);

            return back()->with('success', "{$user->name} has order history, so the account was deactivated instead of deleted.");
        }

        $user->delete();

        return back()->with('success', "{$user->name} was deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(UserRequest $request): array
    {
        return [
            ...$request->safe()->only('name', 'email', 'role', 'is_active'),
            'phone' => $request->filled('phone') ? Phone::normalize($request->validated('phone')) : null,
            ...($request->filled('password') ? ['password' => $request->validated('password')] : []),
        ];
    }

    /**
     * Anyone given the rider role needs a rider profile to receive deliveries.
     */
    private function ensureRiderProfile(User $user): void
    {
        if ($user->role === UserRole::Rider) {
            $user->rider()->firstOrCreate([], ['vehicle_type' => 'Motorcycle', 'is_available' => true]);
        }
    }
}
