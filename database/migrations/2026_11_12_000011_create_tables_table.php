<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hall_id')->constrained()->restrictOnDelete();
            $table->string('number', 10)->unique();
            $table->unsignedTinyInteger('seats');
            $table->unsignedTinyInteger('min_guests')->default(1);
            $table->string('shape', 10);
            $table->unsignedSmallInteger('x')->default(0);
            $table->unsignedSmallInteger('y')->default(0);
            $table->unsignedSmallInteger('width')->default(100);
            $table->unsignedSmallInteger('height')->default(100);
            $table->unsignedSmallInteger('rotation')->default(0);
            $table->string('qr_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
