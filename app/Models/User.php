<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Support\OutletContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'outlet_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * A freshly created account (a customer who just registered, say) has no
     * outlet until an admin assigns one.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['outlet_id' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'outlet_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    /**
     * Staff who work at a single outlet (everyone but super admins and customers).
     */
    public function isOutletStaff(): bool
    {
        return $this->isStaff() && ! $this->isAdmin();
    }

    /**
     * Whether this member of staff may see the given outlet's screens.
     */
    public function worksIn(int $outletId): bool
    {
        $outlets = app(OutletContext::class);

        if (! $outlets->multi()) {
            return $outletId === $outlets->default()->id;
        }

        return $this->isAdmin() || ($this->outlet_id ?? $outlets->default()->id) === $outletId;
    }

    /**
     * The outlet this member of staff works at. Null means the default outlet.
     *
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * Staff assigned to the outlet. Staff without an outlet belong to the
     * default one.
     *
     * @param  Builder<User>  $query
     */
    public function scopeInOutlet(Builder $query, Outlet $outlet): void
    {
        $query->where(fn (Builder $inner) => $inner
            ->where('outlet_id', $outlet->id)
            ->when($outlet->is_default, fn (Builder $default) => $default->orWhereNull('outlet_id')));
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Address, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** @return HasOne<Rider, $this> */
    public function rider(): HasOne
    {
        return $this->hasOne(Rider::class);
    }
}
