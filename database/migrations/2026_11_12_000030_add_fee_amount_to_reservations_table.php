<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Плата за бронювання в копійках, знімок на момент створення; 0 — безкоштовне (частина 2, п. 3.6)
            $table->unsignedInteger('fee_amount')->default(0)->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('fee_amount');
        });
    }
};
