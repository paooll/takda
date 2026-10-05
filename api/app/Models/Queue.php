<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Queue extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'code_prefix',
        'avg_service_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'avg_service_minutes' => 'integer',
            'last_issued_number' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function waitingTickets(): HasMany
    {
        return $this->tickets()->where('status', Ticket::STATUS_WAITING);
    }

    /**
     * Atomically claim the next number for this queue.
     *
     * A read-then-write ("SELECT max() + 1") races when two customers tap
     * "join" at the same instant and would hand out duplicate ticket codes.
     * The increment and the read happen in one UPDATE, so the database
     * serialises them.
     */
    public function issueTicketNumber(): int
    {
        $this->increment('last_issued_number');

        return (int) $this->refresh()->last_issued_number;
    }

    public function formatTicketCode(int $number): string
    {
        return $this->code_prefix.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}
