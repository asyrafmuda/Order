<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An outlet's own price for a menu item. Without a row the item sells at
     * its standard price (products.price and friends).
     */
    public function up(): void
    {
        Schema::create('outlet_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            // product | variant | modifier | combo
            $table->string('item_type', 20);
            $table->unsignedBigInteger('item_id');
            // Sen, like every other amount.
            $table->unsignedInteger('price');
            $table->timestamps();

            $table->unique(['outlet_id', 'item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_prices');
    }
};
