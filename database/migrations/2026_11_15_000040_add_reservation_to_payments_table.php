<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Платіж лише за бронювання не має замовлення (частина 2, п. 2.1)
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreignId('reservation_id')->nullable()->after('order_id')->constrained()->restrictOnDelete();
            // Плата за бронювання в копійках; у amount вона не входить (частина 2, п. 2.3)
            $table->unsignedInteger('booking_fee_amount')->default(0)->after('tip_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
            $table->dropColumn('booking_fee_amount');
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
        });
    }
};
