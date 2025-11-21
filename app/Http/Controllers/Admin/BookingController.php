<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Booking::with('customer')
            ->where('user_id', $user->id)
            ->orderByDesc('event_date');
        if ($search = $request->query('q')) {
            $query->where(function ($q2) use ($search) {
                $q2->where('location', 'like', "%$search%")
                   ->orWhere('status', 'like', "%$search%")
                   ->orWhere('notes', 'like', "%$search%");
            });
        }
        return $query->paginate((int) $request->query('per_page', 10));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'customer_id' => ['required','integer','exists:customers,id'],
            'event_date' => ['required','date'],
            'location' => ['nullable','string','max:255'],
            'status' => ['nullable','string','in:scheduled,completed,cancelled'],
            'notes' => ['nullable','string'],
            'wedding_shoot_date' => ['nullable','date'],
            'preshoot_date' => ['nullable','date'],
            'homecoming_date' => ['nullable','date'],
            'function_date' => ['nullable','date'],
            'event_covering_date' => ['nullable','date'],
            'custom_plan_date' => ['nullable','date'],
            'wedding_shoot_location' => ['nullable','string','max:255'],
            'preshoot_location' => ['nullable','string','max:255'],
            'homecoming_location' => ['nullable','string','max:255'],
            'function_location' => ['nullable','string','max:255'],
            'event_covering_location' => ['nullable','string','max:255'],
            'custom_plan_location' => ['nullable','string','max:255'],
        ]);
        // Ensure the customer belongs to user
        $customer = Customer::where('id', $data['customer_id'])->where('user_id', $user->id)->firstOrFail();
        $data['user_id'] = $user->id;
        $booking = Booking::create($data);
        return response()->json($booking->load('customer'), 201);
    }

    public function show(Request $request, Booking $booking)
    {
        $this->authorizeAccess($request, $booking);
        return $booking->load('customer');
    }

    public function update(Request $request, Booking $booking)
    {
        $this->authorizeAccess($request, $booking);
        $data = $request->validate([
            'customer_id' => ['sometimes','integer','exists:customers,id'],
            'event_date' => ['sometimes','date'],
            'location' => ['nullable','string','max:255'],
            'status' => ['nullable','string','in:scheduled,completed,cancelled'],
            'notes' => ['nullable','string'],
            'wedding_shoot_date' => ['nullable','date'],
            'preshoot_date' => ['nullable','date'],
            'homecoming_date' => ['nullable','date'],
            'function_date' => ['nullable','date'],
            'event_covering_date' => ['nullable','date'],
            'custom_plan_date' => ['nullable','date'],
            'wedding_shoot_location' => ['nullable','string','max:255'],
            'preshoot_location' => ['nullable','string','max:255'],
            'homecoming_location' => ['nullable','string','max:255'],
            'function_location' => ['nullable','string','max:255'],
            'event_covering_location' => ['nullable','string','max:255'],
            'custom_plan_location' => ['nullable','string','max:255'],
        ]);
        if (isset($data['customer_id'])) {
            $user = $request->user();
            Customer::where('id', $data['customer_id'])->where('user_id', $user->id)->firstOrFail();
        }
        $booking->update($data);
        return $booking->load('customer');
    }

    public function destroy(Request $request, Booking $booking)
    {
        $this->authorizeAccess($request, $booking);
        $booking->delete();
        return response()->json(['status' => 'deleted']);
    }

    /**
     * Return bookings within a date range for calendar views.
     */
    public function calendar(Request $request)
    {
        $user = $request->user();
        $start = $request->query('start');
        $end = $request->query('end');
        $request->validate([
            'start' => ['required','date'],
            'end' => ['required','date','after_or_equal:start'],
        ]);

        $bookings = Booking::with('customer')
            ->where('user_id', $user->id)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('event_date', [$start, $end])
                  ->orWhereBetween('wedding_shoot_date', [$start, $end])
                  ->orWhereBetween('preshoot_date', [$start, $end])
                  ->orWhereBetween('homecoming_date', [$start, $end])
                  ->orWhereBetween('function_date', [$start, $end])
                  ->orWhereBetween('event_covering_date', [$start, $end])
                  ->orWhereBetween('custom_plan_date', [$start, $end]);
            })
            ->orderBy('event_date', 'asc')
            ->get();

        $events = [];
        foreach ($bookings as $booking) {
            $dates = [
                'event_date' => ['Wedding Shoot', 'wedding_shoot_location'],
                'wedding_shoot_date' => ['Wedding Shoot', 'wedding_shoot_location'],
                'preshoot_date' => ['Preshoot Day', 'preshoot_location'],
                'homecoming_date' => ['Home Coming Day Shoot', 'homecoming_location'],
                'function_date' => ['Function', 'function_location'],
                'event_covering_date' => ['Event Covering', 'event_covering_location'],
                'custom_plan_date' => ['Custom Plan', 'custom_plan_location'],
            ];
            foreach ($dates as $field => $info) {
                if ($booking->$field && $booking->$field >= $start && $booking->$field <= $end) {
                    $location = $booking->{$info[1]} ? ' @ ' . $booking->{$info[1]} : '';
                    $events[] = [
                        'id' => $booking->id . '_' . $field,
                        'booking_id' => $booking->id,
                        'title' => ($booking->customer?->name ? $booking->customer->name : '#'.$booking->customer_id) . ' — ' . $info[0] . ' (' . $booking->status . ')' . $location,
                        'start' => $booking->$field->toISOString(),
                        'end' => $booking->$field->copy()->addHours(1)->toISOString(),
                        'resource' => $booking->toArray(),
                        'type' => $field,
                    ];
                }
            }
        }

        return response()->json($events);
    }

    private function authorizeAccess(Request $request, Booking $booking): void
    {
        abort_if($booking->user_id !== $request->user()->id, 403);
    }

    /**
     * Mark a reminder as sent now for a booking (creates a reminder record).
     */
    public function sendReminder(Request $request, Booking $booking)
    {
        $this->authorizeAccess($request, $booking);
        $user = $request->user();
        $reminder = Reminder::create([
            'user_id' => $user->id,
            'customer_id' => $booking->customer_id,
            'booking_id' => $booking->id,
            'title' => 'Booking reminder #'.$booking->id,
            'remind_at' => Carbon::now(),
            'sent' => true,
        ]);
        return response()->json($reminder->load(['customer','booking']), 201);
    }
}
