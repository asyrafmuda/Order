<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('business_date');
            $table->unsignedInteger('queue_number');
            $table->string('order_number', 12)->comment('Queue token shown to the customer, e.g. A001');
            $table->string('pickup_code', 12)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone', 20)->index();
            $table->string('customer_email')->nullable();
            $table->string('type', 20);
            $table->string('status', 20)->index();
            $table->string('payment_status', 20)->default('pending');
            $table->string('payment_method', 20);
            $table->string('notes', 500)->nullable();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('delivery_fee')->default(0);
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('tax_amount')->default(0)->comment('SST portion; included in total when tax is inclusive');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('tax_inclusive')->default(true);
            $table->unsignedInteger('total');
            $table->timestamp('estimated_ready_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->string('pos_sync_status', 20)->default('pending');
            $table->string('pos_receipt_number')->nullable();
            $table->timestamp('pos_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['business_date', 'queue_number']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('kitchen_station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_code', 16)->nullable();
            $table->string('name');
            $table->string('variant_name')->nullable();
            $table->string('combo_name')->nullable();
            $table->string('combo_choice')->nullable();
            $table->unsignedInteger('combo_price')->default(0);
            $table->unsignedInteger('base_price')->comment('Product or variant price in sen');
            $table->unsignedInteger('unit_price')->comment('Base + modifiers + combo, in sen');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('line_total');
            $table->string('notes')->nullable();
            $table->string('kitchen_status', 20)->default('queued')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name')->nullable();
            $table->string('name');
            $table->unsignedInteger('price')->default(0);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('gateway', 30);
            $table->string('method', 20);
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('amount');
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 80);
            $table->string('postcode', 10);
            $table->string('notes')->nullable();
            $table->unsignedInteger('fee')->default(0);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
