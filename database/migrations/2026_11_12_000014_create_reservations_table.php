<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('token', 64)->unique();
            $table->foreignId('table_id')->constrained()->restrictOnDelete();
            $table->string('guest_name');
            $table->string('guest_phone', 20);
            $table->string('guest_email')->nullable();
            $table->unsignedTinyInteger('guests_count');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->index();
            $table->text('comment')->nullable();
            $table->string('locale', 5);
            $table->string('source', 10);
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('seated_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['table_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
