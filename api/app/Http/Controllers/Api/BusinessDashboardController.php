<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Notification;
use App\Models\Queue;
use App\Models\Ticket;
use App\Services\TurnNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessDashboardController extends Controller
{
    public function __construct(private readonly TurnNotifier $notifier) {}

    /** Live board for one queue: who is waiting, who is being served. */
    public function board(Request $request, Business $business, Queue $queue): JsonResponse
    {
        $this->authorizeOwnership($request, $business);
        abort_if($queue->business_id !== $business->id, 404);

        $tickets = $queue->tickets()
            ->with('user:id,name')
            ->whereIn('status', [Ticket::STATUS_WAITING, Ticket::STATUS_SERVING])
            ->orderBy('sequence')
            ->get()
            ->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'status' => $ticket->status,
                'customer' => $ticket->user->name,
                'position' => $ticket->position(),
                'joined_at' => $ticket->joined_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'queue' => [
                    'id' => $queue->id,
                    'name' => $queue->name,
                    'avg_service_minutes' => $queue->avg_service_minutes,
                    'waiting_count' => $queue->waitingTickets()->count(),
                ],
                'tickets' => $tickets,
            ],
        ]);
    }

    /** Call the next person in line and notify them. */
    public function callNext(Request $request, Business $business, Queue $queue): JsonResponse
    {
        $this->authorizeOwnership($request, $business);
        abort_if($queue->business_id !== $business->id, 404);

        $ticket = DB::transaction(function () use ($queue) {
            $next = $queue->tickets()
                ->where('status', Ticket::STATUS_WAITING)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->first();

            if (! $next) {
                return null;
            }

            $next->update([
                'status' => Ticket::STATUS_SERVING,
                'called_at' => now(),
            ]);

            return $next;
        });

        if (! $ticket) {
            return response()->json(['message' => 'Nobody is waiting in this queue.'], 404);
        }

        $this->notifier->notifyCalled($ticket->load('queue.business'));

        return response()->json(['data' => ['id' => $ticket->id, 'code' => $ticket->code]]);
    }

    /** Mark the person at the counter as served. */
    public function complete(Request $request, Business $business, Queue $queue, Ticket $ticket): JsonResponse
    {
        $this->authorizeOwnership($request, $business);
        abort_if($ticket->queue_id !== $queue->id, 404);
        abort_if($ticket->status !== Ticket::STATUS_SERVING, 422, 'Only a serving ticket can be completed.');

        $ticket->update([
            'status' => Ticket::STATUS_DONE,
            'completed_at' => now(),
        ]);

        // The next person's estimate just changed; tell them if they are close.
        $this->notifier->notifyNextIfApproaching($queue->load('business'));

        return response()->json(['message' => 'Marked as served.']);
    }

    /** Headline numbers and per-queue throughput for the business dashboard. */
    public function analytics(Request $request, Business $business): JsonResponse
    {
        $this->authorizeOwnership($request, $business);

        $since = now()->subDays(30);

        $queues = $business->queues()->get()->map(fn (Queue $queue) => [
            'id' => $queue->id,
            'name' => $queue->name,
            'waiting' => $queue->waitingTickets()->count(),
            'served_30d' => $queue->tickets()
                ->where('status', Ticket::STATUS_DONE)
                ->where('completed_at', '>=', $since)
                ->count(),
            'avg_service_minutes' => (float) ($queue->tickets()
                ->where('status', Ticket::STATUS_DONE)
                ->whereNotNull('called_at')
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', $since)
                ->get(['called_at', 'completed_at'])
                ->map(fn (Ticket $t) => $t->called_at->diffInMinutes($t->completed_at))
                ->avg() ?? 0),
        ]);

        return response()->json([
            'data' => [
                'served_30d' => array_sum(array_column($queues->all(), 'served_30d')),
                'waiting_now' => array_sum(array_column($queues->all(), 'waiting')),
                'appointments_30d' => $business->appointments()
                    ->where('created_at', '>=', $since)
                    ->count(),
                'no_show_rate' => $this->noShowRate($business, $since),
                'queues' => $queues,
            ],
        ]);
    }

    private function noShowRate(Business $business, $since): float
    {
        $total = $business->appointments()->where('created_at', '>=', $since)->count();

        if ($total === 0) {
            return 0.0;
        }

        $missed = $business->appointments()
            ->where('created_at', '>=', $since)
            ->whereIn('status', [Appointment::STATUS_NO_SHOW, Appointment::STATUS_CANCELLED])
            ->count();

        return round($missed / $total, 3);
    }

    private function authorizeOwnership(Request $request, Business $business): void
    {
        abort_if($business->owner_id !== $request->user()->id, 403, 'Not your business.');
    }
}
