<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('title')->nullable();
            $table->json('subtitle')->nullable();
            $table->json('body')->nullable();
            $table->json('button_label')->nullable();
            $table->string('button_url')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_blocks');
    }
};
