<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dine-in: tables with a QR code customers scan to order from their seat.
     * Customers can also sign in with their mobile number and a PIN, so an
     * email address is no longer required and phone numbers must be unique.
     */
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('token', 32)->unique()->comment('Random value encoded in the table QR code');
            $table->unsignedTinyInteger('seats')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('dining_table_id')->nullable()->after('placed_by_id')->constrained()->nullOnDelete();
            $table->string('table_label', 40)->nullable()->after('dining_table_id')->comment('Table name at the time of the order');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->dropIndex(['phone']);
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->index('phone');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dining_table_id');
            $table->dropColumn('table_label');
        });

        Schema::dropIfExists('dining_tables');
    }
};
