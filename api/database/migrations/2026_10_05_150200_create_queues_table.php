<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            // Short code shown on the ticket, e.g. "A-042".
            $table->string('code_prefix', 4)->default('A');
            $table->unsignedSmallInteger('avg_service_minutes')->default(10);
            $table->boolean('is_active')->default(true);
            // Monotonic counter backing ticket numbers without a race-prone max()+1.
            $table->unsignedBigInteger('last_issued_number')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};
