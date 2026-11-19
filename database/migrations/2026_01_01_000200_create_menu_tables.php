<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 16)->nullable();
            $table->string('eyebrow')->nullable();
            $table->string('tagline')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('kitchen_station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 16)->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('price')->comment('Base price in sen');
            $table->string('status', 20)->default('available')->index();
            $table->string('badge', 40)->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_combo_eligible')->default(false);
            $table->boolean('is_upsell')->default(false);
            $table->unsignedSmallInteger('prep_minutes')->default(10);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('pos_item_id')->nullable()->index();
            $table->string('pos_variant_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('price')->comment('Full price in sen for this variant');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('pos_variant_id')->nullable();
            $table->timestamps();
        });

        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('price')->default(0)->comment('Extra charge in sen');
            $table->boolean('is_available')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('pos_modifier_option_id')->nullable();
            $table->timestamps();
        });

        Schema::create('modifier_group_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'modifier_group_id']);
        });

        Schema::create('combo_offers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('price')->comment('Upgrade price in sen');
            $table->json('choices')->nullable()->comment('Drink / side choices offered');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_offers');
        Schema::dropIfExists('modifier_group_product');
        Schema::dropIfExists('modifiers');
        Schema::dropIfExists('modifier_groups');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('kitchen_stations');
    }
};
