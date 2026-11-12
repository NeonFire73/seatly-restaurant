<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->constrained()->restrictOnDelete();
            // Знімок назви обома мовами: зміна меню не має змінювати старі рахунки
            $table->json('name');
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('quantity');
            $table->string('status', 20)->index();
            $table->string('comment')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
