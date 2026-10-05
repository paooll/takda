<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Queue;
use App\Models\Ticket;
use App\Services\WaitTimeEstimator;
use Illuminate\Support\Facades\DB;

/**
 * Writes the in-app alerts customers rely on to leave the counter in time.
 *
 * ponytail: database notifications only. There is no push/email transport yet;
 * add one behind this class so callers do not change.
 */
class TurnNotifier
{
    /** Notify once the customer is within this many places of the front. */
    public const APPROACHING_THRESHOLD = 3;

    public function __construct(private readonly WaitTimeEstimator $estimator) {}

    public function notifyCalled(Ticket $ticket): Notification
    {
        $business = $ticket->queue->business;

        return $this->record(
            $ticket->user_id,
            Notification::TYPE_CALLED,
            "You're up at {$business->name}",
            "Ticket {$ticket->code} — please proceed to the counter.",
            ['ticket_id' => $ticket->id, 'code' => $ticket->code],
        );
    }

    /**
     * Alert the next customer if they are close to the front.
     *
     * Guarded so repeated calls (every "served" tap) do not spam the same
     * person with a duplicate alert.
     */
    public function notifyNextIfApproaching(Queue $queue): ?Notification
    {
        $next = $queue->waitingTickets()->orderBy('sequence')->first();

        if (! $next) {
            return null;
        }

        $position = $next->position();

        if ($position > self::APPROACHING_THRESHOLD) {
            return null;
        }

        $alreadyNotified = Notification::query()
            ->where('user_id', $next->user_id)
            ->where('type', Notification::TYPE_TURN_APPROACHING)
            ->whereJsonContains('data->ticket_id', $next->id)
            ->exists();

        if ($alreadyNotified) {
            return null;
        }

        $waitMinutes = (int) round(
            $this->estimator->estimateSeconds($queue, max(0, $position - 1)) / 60
        );

        return $this->record(
            $next->user_id,
            Notification::TYPE_TURN_APPROACHING,
            $position === 1 ? 'You are next in line' : "You're #$position in line",
            $position === 1
                ? 'Please head to the counter now.'
                : "About {$waitMinutes} min until your turn at {$queue->business->name}.",
            ['ticket_id' => $next->id, 'position' => $position],
        );
    }

    private function record(
        int $userId,
        string $type,
        string $title,
        string $body,
        array $data,
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
