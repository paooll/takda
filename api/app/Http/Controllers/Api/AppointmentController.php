<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\PaginatedResponse;
use App\Models\Appointment;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return PaginatedResponse::make(
            $request->user()
                ->appointments()
                ->with('business:id,name,slug')
                ->orderBy('starts_at')
                ->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $conflict = Appointment::query()
            ->where('business_id', $data['business_id'])
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('starts_at', $data['starts_at'])
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'That slot is already taken.',
                'errors' => ['starts_at' => ['This time slot is already booked.']],
            ], 422);
        }

        $appointment = Appointment::create([
            ...$data,
            'user_id' => $request->user()->id,
            'reference' => strtoupper(Str::random(8)),
        ]);

        // `fresh()` so DB-defaulted columns (status, ends_at) are present in the
        // response; the in-memory model returned by create() omits them.
        return response()->json([
            'data' => $appointment->fresh()->load('business:id,name,slug'),
        ], 201);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);

        $appointment->update($request->validate([
            'status' => ['required', Rule::in([
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_CHECKED_IN,
                Appointment::STATUS_DONE,
                Appointment::STATUS_CANCELLED,
                Appointment::STATUS_NO_SHOW,
            ])],
        ]));

        return response()->json(['data' => $appointment->fresh()]);
    }

    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);

        $appointment->update(['status' => Appointment::STATUS_CANCELLED]);

        return response()->json(['message' => 'Appointment cancelled.']);
    }

    private function authorizeAppointment(Request $request, Appointment $appointment): void
    {
        abort_if($appointment->user_id !== $request->user()->id, 403, 'Not your appointment.');
    }
}
