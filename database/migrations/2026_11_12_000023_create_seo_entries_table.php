<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_entries', function (Blueprint $table) {
            $table->id();
            $table->string('page_key', 30)->unique();
            $table->json('title')->nullable();
            $table->json('description')->nullable();
            $table->string('og_image')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_entries');
    }
};
