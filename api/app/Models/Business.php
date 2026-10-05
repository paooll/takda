<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'address',
        'timezone',
        'avg_service_minutes',
        'is_open',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'avg_service_minutes' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** Queues a customer can currently join. */
    public function activeQueues(): HasMany
    {
        return $this->queues()->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
