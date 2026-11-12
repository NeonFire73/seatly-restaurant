<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('halls', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('plan_width')->default(1200);
            $table->unsignedSmallInteger('plan_height')->default(800);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('halls');
    }
};
