<?php

namespace App\Http\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * One shape for every list endpoint.
 *
 * Laravel's default paginator nests the rows under `data.data`, which means two
 * endpoints can both return a top-level `data` key with different shapes. This
 * flattens rows to `data` and moves pagination to `meta`, so the client can
 * treat every list the same way.
 */
class PaginatedResponse
{
    public static function make(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
