<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orders can now be keyed in by staff at the counter (POS). Walk-in
     * customers do not have to give a phone number.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('source', 20)->default('online')->after('type')->index();
            $table->foreignId('placed_by_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('customer_phone', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('placed_by_id');
            $table->dropColumn('source');
        });
    }
};
