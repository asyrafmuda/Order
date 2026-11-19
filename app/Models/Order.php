<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SyncStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'uuid', 'business_date', 'queue_number', 'order_number', 'pickup_code', 'user_id', 'customer_name',
    'customer_phone', 'customer_email', 'type', 'status', 'payment_status', 'payment_method', 'notes',
    'subtotal', 'delivery_fee', 'discount', 'tax_amount', 'tax_rate', 'tax_inclusive', 'total',
    'estimated_ready_at', 'confirmed_at', 'preparing_at', 'ready_at', 'dispatched_at', 'completed_at',
    'cancelled_at', 'cancel_reason', 'pos_sync_status', 'pos_receipt_number', 'pos_synced_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'pos_sync_status' => SyncStatus::class,
            'tax_rate' => 'float',
            'tax_inclusive' => 'boolean',
            'estimated_ready_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'preparing_at' => 'datetime',
            'ready_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'pos_synced_at' => 'datetime',
        ];
    }

    /**
     * The trading day the order belongs to. Stored as a plain Y-m-d string so
     * equality checks behave the same on MySQL and SQLite.
     *
     * @return Attribute<CarbonImmutable|null, string|null>
     */
    protected function businessDate(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? CarbonImmutable::parse($value)->startOfDay() : null,
            set: fn (mixed $value) => $value ? CarbonImmutable::parse($value)->toDateString() : null,
        );
    }

    /**
     * Orders are addressed by UUID in URLs so guests can open their ticket
     * without the URL being guessable.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<Payment, $this> */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /** @return HasOne<Delivery, $this> */
    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    /** @return HasMany<PosSyncLog, $this> */
    public function posSyncLogs(): HasMany
    {
        return $this->hasMany(PosSyncLog::class);
    }

    /** @return HasMany<NotificationLog, $this> */
    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function isDelivery(): bool
    {
        return $this->type === OrderType::Delivery;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', OrderStatus::active());
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopeInKitchen(Builder $query): void
    {
        $query->whereIn('status', OrderStatus::kitchenQueue());
    }

    /**
     * Orders that count towards sales figures.
     *
     * @param  Builder<Order>  $query
     */
    public function scopePaid(Builder $query): void
    {
        $query->where('payment_status', PaymentStatus::Paid);
    }
}
