<?php

namespace App\Services;

use App\Models\Queue;
use App\Models\Ticket;

/**
 * Turns a place-in-line into an estimated wait.
 *
 * ponytail: a linear "people ahead x average service time" model. It ignores
 * the counter currently being served and per-staff capacity, so a queue with
 * three tellers will overestimate. Upgrade to a rolling median of recent
 * actual service times once there is enough history to be worth it.
 */
class WaitTimeEstimator
{
    /** Fallback when a queue has no configured average. */
    private const DEFAULT_SERVICE_MINUTES = 10;

    public function estimateSeconds(Queue $queue, int $peopleAhead): int
    {
        $minutesPerPerson = $this->observedMinutes($queue)
            ?? $queue->avg_service_minutes
            ?: self::DEFAULT_SERVICE_MINUTES;

        return $peopleAhead * $minutesPerPerson * 60;
    }

    /**
     * Estimate for a ticket that has not been persisted yet, where the caller's
     * position is known but the ticket's own sequence does not exist.
     */
    public function estimateForNewTicket(Queue $queue, int $position): int
    {
        return $this->estimateSeconds($queue, max(0, $position - 1));
    }

    /**
     * Median service time of recently completed tickets, in minutes.
     *
     * Returns null below the sample floor: a single slow appointment should not
     * dictate the estimate for the whole queue.
     */
    private function observedMinutes(Queue $queue): ?float
    {
        $sampleFloor = 5;

        $durations = Ticket::query()
            ->where('queue_id', $queue->id)
            ->where('status', Ticket::STATUS_DONE)
            ->whereNotNull('called_at')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(20)
            ->get(['called_at', 'completed_at'])
            ->map(fn (Ticket $ticket) => $ticket->called_at->diffInMinutes($ticket->completed_at))
            ->filter(fn (int $minutes) => $minutes > 0)
            ->sort()
            ->values();

        if ($durations->count() < $sampleFloor) {
            return null;
        }

        return (float) $this->median($durations);
    }

    /**
     * Median of a sorted list, averaging the middle pair on even counts.
     * Collection::middle() is not available on this framework version.
     */
    private function median(\Illuminate\Support\Collection $sorted): float
    {
        $count = $sorted->count();

        if ($count % 2 === 1) {
            return (float) $sorted->values()[(int) floor($count / 2)];
        }

        $middle = intdiv($count, 2);

        return ($sorted->values()[$middle - 1] + $sorted->values()[$middle]) / 2;
    }
}
