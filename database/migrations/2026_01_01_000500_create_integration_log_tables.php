<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('action', 40);
            $table->string('status', 20)->index();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 30);
            $table->string('recipient', 40);
            $table->string('template', 40);
            $table->text('message');
            $table->string('status', 20)->index();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('pos_sync_logs');
    }
};
