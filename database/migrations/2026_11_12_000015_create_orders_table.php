<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('table_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 10);
            $table->string('status', 20);
            $table->dateTime('serve_at')->nullable();
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('paid_total')->default(0);
            $table->text('comment')->nullable();
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['table_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
