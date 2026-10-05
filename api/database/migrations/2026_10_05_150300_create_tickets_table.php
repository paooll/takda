<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Immutable join order. Position is derived from this, never stored,
            // so calling someone never forces a renumber of everyone behind them.
            $table->unsignedBigInteger('sequence');
            $table->string('code', 16)->unique();
            $table->string('status')->default('waiting');
            $table->unsignedInteger('estimated_wait_seconds')->nullable();
            $table->timestamp('joined_at');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // The hot path: "how many people are ahead of me in this queue".
            $table->index(['queue_id', 'status', 'sequence']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
