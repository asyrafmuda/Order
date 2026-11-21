<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * License server only: the purchase codes sold, and where each is activated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('product', 60);
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            // How many domains may use the code at the same time.
            $table->unsignedSmallInteger('max_activations')->default(1);
            $table->text('notes')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('version', 20)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('activated_at');
            $table->timestamp('last_checked_at')->nullable();
            // Set when the domain is released; the row stays as history.
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->index(['license_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_activations');
        Schema::dropIfExists('licenses');
    }
};
