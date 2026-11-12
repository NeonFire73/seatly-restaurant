<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('method', 10);
            $table->string('provider', 20)->nullable();
            $table->string('provider_invoice_id')->nullable()->unique();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('tip_amount')->default(0);
            $table->char('currency', 3)->default('UAH');
            $table->string('status', 20)->index();
            $table->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('page_url')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->unsignedInteger('refunded_amount')->default(0);
            // Мілісекунди потрібні, щоб правильно впорядковувати вебхуки, які прийшли майже одночасно
            $table->dateTime('provider_modified_at', 6)->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
