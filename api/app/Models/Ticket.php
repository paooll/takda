<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_SERVING = 'serving';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'queue_id',
        'user_id',
        'sequence',
        'code',
        'status',
        'estimated_wait_seconds',
        'joined_at',
        'called_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'estimated_wait_seconds' => 'integer',
            'joined_at' => 'datetime',
            'called_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_WAITING, self::STATUS_SERVING]);
    }

    /**
     * 1-based place in line: how many active tickets were issued before this one.
     *
     * Derived from `sequence` rather than stored, so a ticket keeps its number
     * when people ahead of it are called or drop out.
     */
    public function position(): int
    {
        if ($this->status !== self::STATUS_WAITING) {
            return 0;
        }

        $ahead = static::query()
            ->where('queue_id', $this->queue_id)
            ->where('status', self::STATUS_WAITING)
            ->where('sequence', '<', $this->sequence)
            ->count();

        return $ahead + 1;
    }
}
