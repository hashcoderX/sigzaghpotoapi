<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Reminder::with(['customer','booking'])
            ->where('user_id', $user->id)
            ->orderBy('remind_at');
        if ($search = $request->query('q')) {
            $query->where(function ($q2) use ($search) {
                $q2->where('title', 'like', "%$search%")
                   ->orWhere('sent', 'like', "%$search%");
            });
        }
        return $query->paginate((int) $request->query('per_page', 10));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'remind_at' => ['required','date'],
            'customer_id' => ['nullable','integer','exists:customers,id'],
            'booking_id' => ['nullable','integer','exists:bookings,id'],
            'sent' => ['nullable','boolean'],
        ]);
        if (!empty($data['customer_id'])) {
            Customer::where('id', $data['customer_id'])->where('user_id', $user->id)->firstOrFail();
        }
        if (!empty($data['booking_id'])) {
            Booking::where('id', $data['booking_id'])->where('user_id', $user->id)->firstOrFail();
        }
        $data['user_id'] = $user->id;
        $reminder = Reminder::create($data);
        return response()->json($reminder->load(['customer','booking']), 201);
    }

    public function show(Request $request, Reminder $reminder)
    {
        $this->authorizeAccess($request, $reminder);
        return $reminder->load(['customer','booking']);
    }

    public function update(Request $request, Reminder $reminder)
    {
        $this->authorizeAccess($request, $reminder);
        $data = $request->validate([
            'title' => ['sometimes','string','max:255'],
            'remind_at' => ['sometimes','date'],
            'customer_id' => ['nullable','integer','exists:customers,id'],
            'booking_id' => ['nullable','integer','exists:bookings,id'],
            'sent' => ['nullable','boolean'],
        ]);
        if (isset($data['customer_id'])) {
            Customer::where('id', $data['customer_id'])->where('user_id', $request->user()->id)->firstOrFail();
        }
        if (isset($data['booking_id'])) {
            Booking::where('id', $data['booking_id'])->where('user_id', $request->user()->id)->firstOrFail();
        }
        $reminder->update($data);
        return $reminder->load(['customer','booking']);
    }

    public function destroy(Request $request, Reminder $reminder)
    {
        $this->authorizeAccess($request, $reminder);
        $reminder->delete();
        return response()->json(['status' => 'deleted']);
    }

    private function authorizeAccess(Request $request, Reminder $reminder): void
    {
        abort_if($reminder->user_id !== $request->user()->id, 403);
    }

    /**
     * Return mapping of booking_id => sent_today (boolean) for provided booking IDs.
     * Query param: booking_ids=1,2,3
     */
    public function sentToday(Request $request)
    {
        $user = $request->user();
        $idsParam = $request->query('booking_ids', '');
        $ids = collect(explode(',', (string)$idsParam))
            ->map(fn($v) => (int) trim($v))
            ->filter(fn($v) => $v > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $start = Carbon::today();
        $end = Carbon::today()->endOfDay();

        $rows = Reminder::query()
            ->select('booking_id')
            ->where('user_id', $user->id)
            ->whereIn('booking_id', $ids)
            ->where('sent', true)
            ->whereBetween('remind_at', [$start, $end])
            ->groupBy('booking_id')
            ->pluck('booking_id')
            ->all();

        $set = collect($rows)->flip();
        $result = $ids->map(fn($bid) => [
            'booking_id' => $bid,
            'sent_today' => $set->has($bid),
        ]);

        return response()->json(['data' => $result]);
    }
}
