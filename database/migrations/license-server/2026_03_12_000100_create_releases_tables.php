<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * License server only: the update packages customers' sites can install with
 * one click, who may install each one, and until when a code gets updates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->string('product', 60);
            $table->string('version', 20);
            $table->text('notes')->nullable();
            // Where the ZIP is kept on the license server's private disk.
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            // "all" customers, or only the licenses picked for it.
            $table->string('audience', 10)->default('all');
            // Drafts are invisible to customers.
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['product', 'version']);
        });

        Schema::create('license_release', function (Blueprint $table) {
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->primary(['license_id', 'release_id']);
        });

        Schema::table('licenses', function (Blueprint $table) {
            // Releases published after this day are not offered. Empty = for life.
            $table->date('updates_until')->nullable()->after('max_activations');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', fn (Blueprint $table) => $table->dropColumn('updates_until'));
        Schema::dropIfExists('license_release');
        Schema::dropIfExists('releases');
    }
};
