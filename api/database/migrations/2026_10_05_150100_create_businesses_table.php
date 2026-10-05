<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            // Stable public identifier used in QR join links (/j/{slug}).
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('address')->nullable();
            $table->string('timezone')->default('Asia/Manila');
            // Fallback service duration when a queue does not override it.
            $table->unsignedSmallInteger('avg_service_minutes')->default(10);
            $table->boolean('is_open')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
