<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_category_id')->constrained()->restrictOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('weight')->nullable();
            $table->string('unit', 5)->nullable();
            $table->unsignedSmallInteger('cook_time_minutes')->default(15);
            $table->json('tags')->nullable();
            $table->json('allergens')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_preorderable')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dishes');
    }
};
