<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('areas')->nullable()->comment('Human readable coverage, e.g. postcodes or neighbourhoods');
            $table->unsignedInteger('fee')->comment('Delivery fee in sen');
            $table->unsignedInteger('min_order')->default(0)->comment('Minimum subtotal in sen');
            $table->unsignedSmallInteger('eta_minutes')->default(40);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 40)->default('Home');
            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 80);
            $table->string('postcode', 10);
            $table->string('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('vehicle_type', 40)->default('Motorcycle');
            $table->string('plate_number', 20)->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riders');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('delivery_zones');
    }
};
