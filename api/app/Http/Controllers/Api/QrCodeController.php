<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Queue;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QrCodeController extends Controller
{
    /**
     * QR code a customer scans to join this queue with one tap.
     *
     * The payload is a deep link to the join page, not a raw queue id, so the
     * printed poster keeps working if the frontend route ever changes.
     */
    public function show(Request $request, Business $business, Queue $queue): JsonResponse
    {
        abort_if($queue->business_id !== $business->id, 404);

        $isOwner = $business->owner_id === $request->user()?->id;
        $url = $this->joinUrl($business, $queue);

        return response()->json([
            'data' => [
                'url' => $url,
                'svg' => $this->renderSvg($url),
            ],
            'meta' => [
                'queue' => $queue->name,
                'business' => $business->name,
                'is_owner' => $isOwner,
            ],
        ]);
    }

    /** Same code as an SVG image, for embedding directly in a poster or page. */
    public function image(Request $request, Business $business, Queue $queue): Response
    {
        abort_if($queue->business_id !== $business->id, 404);

        return response($this->renderSvg($this->joinUrl($business, $queue)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function joinUrl(Business $business, Queue $queue): string
    {
        $frontend = rtrim(config('takda.frontend_url'), '/');

        return "{$frontend}/j/{$business->slug}/{$queue->id}";
    }

    private function renderSvg(string $url): string
    {
        $result = new Builder(
            writer: new SvgWriter,
            data: $url,
            size: 320,
            margin: 16,
        );

        return $result->build()->getString();
    }
}
