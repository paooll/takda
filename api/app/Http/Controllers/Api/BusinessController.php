<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Queue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
    /** Public directory of joinable businesses. */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Business::query()
                ->where('is_open', true)
                ->with(['activeQueues' => fn ($q) => $q->orderBy('name')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'avg_service_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
        ]);

        $business = $request->user()->businesses()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
        ]);

        return response()->json(['data' => $business->fresh()], 201);
    }

    public function show(Business $business): JsonResponse
    {
        abort_unless($business->is_open, 404);

        return response()->json([
            'data' => $business->load('activeQueues'),
        ]);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        $this->authorizeOwnership($request, $business);

        $business->update($request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'address' => ['nullable', 'string', 'max:255'],
            'avg_service_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'is_open' => ['sometimes', 'boolean'],
        ]));

        return response()->json(['data' => $business->fresh()]);
    }

    private function authorizeOwnership(Request $request, Business $business): void
    {
        abort_if($business->owner_id !== $request->user()->id, 403, 'Not your business.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;
        $suffix = 1;

        while (Business::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
