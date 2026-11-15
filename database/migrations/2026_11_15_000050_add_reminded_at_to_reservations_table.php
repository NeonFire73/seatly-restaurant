<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Коли гостеві надіслано лист-нагадування; за цією позначкою він іде лише раз (частина 2, розділ 8)
            $table->timestamp('reminded_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
