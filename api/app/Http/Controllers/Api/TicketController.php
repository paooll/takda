<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Queue;
use App\Models\Ticket;
use App\Services\WaitTimeEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function __construct(private readonly WaitTimeEstimator $estimator) {}

    /**
     * Join a queue and get a digital ticket.
     *
     * The whole join is one transaction so a customer can never end up with a
     * consumed ticket number but no ticket, or two active tickets by racing
     * their own double-tap.
     */
    public function store(Request $request, Queue $queue): JsonResponse
    {
        $user = $request->user();

        abort_if(! $queue->is_active, 422, 'This queue is not accepting customers.');
        abort_if(! $queue->business->is_open, 422, 'This business is closed.');

        $existing = $user->tickets()->active()->where('queue_id', $queue->id)->first();

        if ($existing) {
            return response()->json([
                'data' => $this->present($existing),
                'message' => 'You already hold a ticket in this queue.',
            ], 200);
        }

        $ticket = DB::transaction(function () use ($queue, $user) {
            $number = $queue->issueTicketNumber();
            $waitingAhead = Ticket::query()
                ->where('queue_id', $queue->id)
                ->where('status', Ticket::STATUS_WAITING)
                ->count();

            return Ticket::create([
                'queue_id' => $queue->id,
                'user_id' => $user->id,
                'sequence' => $number,
                'code' => $queue->formatTicketCode($number),
                'status' => Ticket::STATUS_WAITING,
                // The new ticket is last, so everyone already waiting is ahead.
                'estimated_wait_seconds' => $this->estimator->estimateForNewTicket($queue, $waitingAhead + 1),
                'joined_at' => now(),
            ]);
        });

        $ticket->fresh()->load('queue.business');

        return response()->json(['data' => $this->present($ticket)], 201);
    }

    /** Live view of one ticket: position, ETA, and who is being served. */
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicket($request, $ticket);

        return response()->json(['data' => $this->present($ticket->load('queue.business'))]);
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()
            ->tickets()
            ->with('queue.business')
            ->latest('joined_at')
            ->paginate(20);

        return response()->json([
            'data' => array_map(
                fn (Ticket $ticket) => $this->present($ticket),
                $tickets->items(),
            ),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /** Drop out of the queue without using the turn. */
    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicket($request, $ticket);

        abort_if($ticket->status === Ticket::STATUS_DONE, 422, 'This ticket is already completed.');

        $ticket->update(['status' => Ticket::STATUS_CANCELLED]);

        return response()->json(['message' => 'You have left the queue.']);
    }

    private function authorizeTicket(Request $request, Ticket $ticket): void
    {
        abort_if($ticket->user_id !== $request->user()->id, 403, 'Not your ticket.');
    }

    /**
     * Shape a ticket for the client, recomputing position and ETA live.
     *
     * The stored `estimated_wait_seconds` is a snapshot from join time; the
     * displayed value is always recalculated so it shrinks as the line moves.
     */
    private function present(Ticket $ticket): array
    {
        $position = $ticket->position();
        $estimator = $this->estimator;

        $serving = Ticket::query()
            ->where('queue_id', $ticket->queue_id)
            ->where('status', Ticket::STATUS_SERVING)
            ->orderBy('called_at')
            ->get(['id', 'code']);

        return [
            'id' => $ticket->id,
            'code' => $ticket->code,
            'status' => $ticket->status,
            'position' => $position,
            'people_ahead' => max(0, $position - 1),
            'estimated_wait_seconds' => $position > 0
                ? $estimator->estimateSeconds($ticket->queue, max(0, $position - 1))
                : 0,
            'joined_at' => $ticket->joined_at?->toIso8601String(),
            'called_at' => $ticket->called_at?->toIso8601String(),
            'queue' => [
                'id' => $ticket->queue->id,
                'name' => $ticket->queue->name,
                'avg_service_minutes' => $ticket->queue->avg_service_minutes,
            ],
            'business' => [
                'id' => $ticket->queue->business->id,
                'name' => $ticket->queue->business->name,
                'slug' => $ticket->queue->business->slug,
            ],
            'now_serving' => $serving->pluck('code')->all(),
        ];
    }
}
