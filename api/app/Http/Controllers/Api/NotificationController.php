<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\PaginatedResponse;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return PaginatedResponse::make(
            $request->user()->notifications()->latest()->paginate(30)
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread' => $request->user()->notifications()->unread()->count(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_if($notification->user_id !== $request->user()->id, 403, 'Not your notification.');

        $notification->update(['read_at' => now()]);

        return response()->json(['data' => $notification->fresh()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->notifications()->unread()->update(['read_at' => now()]);

        return response()->json(['message' => 'All caught up.']);
    }
}
