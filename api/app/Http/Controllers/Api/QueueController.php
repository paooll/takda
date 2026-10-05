<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Queue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function index(Business $business): JsonResponse
    {
        return response()->json([
            'data' => $business->activeQueues()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        abort_if($business->owner_id !== $request->user()->id, 403, 'Not your business.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'code_prefix' => ['nullable', 'string', 'max:4', 'alpha'],
            'avg_service_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
        ]);

        $queue = $business->queues()->create([
            ...$data,
            'code_prefix' => strtoupper($data['code_prefix'] ?? 'A'),
            'avg_service_minutes' => $data['avg_service_minutes']
                ?? $business->avg_service_minutes,
        ]);

        return response()->json(['data' => $queue->fresh()], 201);
    }

    public function update(Request $request, Business $business, Queue $queue): JsonResponse
    {
        abort_if($business->owner_id !== $request->user()->id, 403, 'Not your business.');
        abort_if($queue->business_id !== $business->id, 404);

        $queue->update($request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'avg_service_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return response()->json(['data' => $queue->fresh()]);
    }
}
