<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings that describe one outlet rather than the whole business.
     * Existing values move from "settings" to the first outlet.
     */
    private const OUTLET_KEYS = [
        'shop.address', 'shop.phone', 'shop.whatsapp', 'shop.maps_url', 'shop.counter',
        'hours.open', 'hours.close', 'hours.last_order',
        'ordering.enabled', 'ordering.pickup_enabled', 'ordering.delivery_enabled', 'ordering.dine_in_enabled',
        'ordering.pickup_eta_min', 'ordering.pickup_eta_max', 'ordering.queue_prefix',
    ];

    /**
     * Multi-outlet. A business always has at least one outlet; everything
     * that happens in a branch (orders, tables, delivery areas, staff, what
     * has sold out) belongs to it. The menu, prices and customers are shared.
     */
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('outlet_settings', function (Blueprint $table) {
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->primary(['outlet_id', 'key']);
        });

        Schema::create('outlet_sold_out_products', function (Blueprint $table) {
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['outlet_id', 'product_id']);
        });

        $outletId = DB::table('outlets')->insertGetId([
            'name' => 'Main Outlet',
            'slug' => 'main',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Per-outlet settings saved so far belong to the first outlet.
        DB::table('settings')->whereIn('key', self::OUTLET_KEYS)->orderBy('key')->get()->each(function ($row) use ($outletId) {
            DB::table('outlet_settings')->insert([
                'outlet_id' => $outletId,
                'key' => $row->key,
                'value' => $row->value,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        });
        DB::table('settings')->whereIn('key', self::OUTLET_KEYS)->delete();

        foreach (['orders', 'dining_tables', 'delivery_zones'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('outlet_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            });

            DB::table($name)->update(['outlet_id' => $outletId]);

            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('outlet_id')->nullable(false)->change();
            });
        }

        // Queue numbers restart every day in each outlet.
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['business_date', 'queue_number']);
            $table->unique(['outlet_id', 'business_date', 'queue_number']);
        });

        // Staff work at one outlet. Null means the default outlet (and, for
        // super admins and customers, no particular outlet).
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('outlet_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('outlet_id');
        });

        $defaultId = DB::table('outlets')->where('is_default', true)->value('id');

        DB::table('outlet_settings')->where('outlet_id', $defaultId)->orderBy('key')->get()->each(function ($row) {
            DB::table('settings')->updateOrInsert(['key' => $row->key], [
                'value' => $row->value,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        });

        // The foreign key goes first: MySQL uses the outlet/date/queue index
        // to enforce it and refuses to drop that index while the key exists.
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['outlet_id', 'business_date', 'queue_number']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('outlet_id');
        });

        // Two outlets may have used the same queue number on the same day, in
        // which case the old one-outlet rule can no longer be put back.
        $clash = DB::table('orders')
            ->select('business_date', 'queue_number')
            ->groupBy('business_date', 'queue_number')
            ->havingRaw('count(*) > 1')
            ->exists();

        Schema::table('orders', function (Blueprint $table) use ($clash) {
            $clash ? $table->index(['business_date', 'queue_number']) : $table->unique(['business_date', 'queue_number']);
        });

        foreach (['dining_tables', 'delivery_zones'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('outlet_id');
            });
        }

        Schema::dropIfExists('outlet_sold_out_products');
        Schema::dropIfExists('outlet_settings');
        Schema::dropIfExists('outlets');
    }
};
