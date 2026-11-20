<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Every outlet gets a "shop QR": a code on the counter that customers
     * scan to order on the spot. A kiosk with no tables needs only this one.
     */
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->string('qr_token', 16)->nullable()->unique()->after('slug');
        });

        DB::table('outlets')->orderBy('id')->pluck('id')->each(
            fn ($id) => DB::table('outlets')->where('id', $id)->update(['qr_token' => Str::lower(Str::random(16))]),
        );
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropUnique(['qr_token']);
            $table->dropColumn('qr_token');
        });
    }
};
