<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Payment::with(['customer','booking'])
            ->where('user_id', $user->id)
            ->orderByDesc('id');
        if ($search = $request->query('q')) {
            $query->where(function ($q2) use ($search) {
                $q2->where('status', 'like', "%$search%")
                   ->orWhere('method', 'like', "%$search%")
                   ->orWhere('amount', 'like', "%$search%");
            });
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', (int)$customerId);
        }
        if ($bookingId = $request->query('booking_id')) {
            $query->where('booking_id', (int)$bookingId);
        }
        if ($invoiceId = $request->query('invoice_id')) {
            $query->where('invoice_id', (int)$invoiceId);
        }
        if ($jobCardId = $request->query('job_card_id')) {
            $query->where('job_card_id', (int)$jobCardId);
        }
        return $query->paginate((int) $request->query('per_page', 10));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'customer_id' => ['required','integer','exists:customers,id'],
            'booking_id' => ['nullable','integer','exists:bookings,id'],
            'invoice_id' => ['nullable','integer','exists:invoices,id'],
            'job_card_id' => ['nullable','integer','exists:job_cards,id'],
            'amount' => ['required','numeric','min:0'],
            'currency' => ['nullable','string','size:3'],
            'method' => ['nullable','string','max:100'],
            'reference' => ['nullable','string','max:255'],
            'status' => ['nullable','string','in:pending,paid,failed'],
            'paid_at' => ['nullable','date'],
        ]);
        Customer::where('id', $data['customer_id'])->where('user_id', $user->id)->firstOrFail();
        if (!empty($data['booking_id'])) {
            Booking::where('id', $data['booking_id'])->where('user_id', $user->id)->firstOrFail();
        }
        $data['user_id'] = $user->id;
    $payment = Payment::create($data);
    $this->recalculateLinked($payment->id);
    return response()->json($payment->load(['customer','booking','invoice','jobCard']), 201);
    }

    public function show(Request $request, Payment $payment)
    {
        $this->authorizeAccess($request, $payment);
        return $payment->load(['customer','booking']);
    }

    public function update(Request $request, Payment $payment)
    {
        $this->authorizeAccess($request, $payment);
        $data = $request->validate([
            'customer_id' => ['sometimes','integer','exists:customers,id'],
            'booking_id' => ['nullable','integer','exists:bookings,id'],
            'amount' => ['sometimes','numeric','min:0'],
            'currency' => ['nullable','string','size:3'],
            'method' => ['nullable','string','max:100'],
            'status' => ['nullable','string','in:pending,paid,failed'],
            'paid_at' => ['nullable','date'],
        ]);
        if (isset($data['customer_id'])) {
            Customer::where('id', $data['customer_id'])->where('user_id', $request->user()->id)->firstOrFail();
        }
        if (isset($data['booking_id'])) {
            Booking::where('id', $data['booking_id'])->where('user_id', $request->user()->id)->firstOrFail();
        }
    $payment->update($data);
    $this->recalculateLinked($payment->id);
    return $payment->load(['customer','booking','invoice','jobCard']);
    }

    public function destroy(Request $request, Payment $payment)
    {
        $this->authorizeAccess($request, $payment);
        $paymentId = $payment->id;
        $invoiceId = $payment->invoice_id; $jobCardId = $payment->job_card_id;
        $payment->delete();
        $this->recalculateLinked(null, $invoiceId, $jobCardId);
        return response()->json(['status' => 'deleted']);
    }

    private function recalculateLinked(?int $paymentId = null, ?int $invoiceId = null, ?int $jobCardId = null): void
    {
        if ($paymentId) {
            $p = Payment::find($paymentId);
            if ($p) { $invoiceId = $invoiceId ?? $p->invoice_id; $jobCardId = $jobCardId ?? $p->job_card_id; }
        }
        if ($invoiceId) {
            $invoice = \App\Models\Invoice::find($invoiceId);
            if ($invoice) {
                $paid = Payment::where('invoice_id', $invoice->id)->where('status','paid')->sum('amount');
                $invoice->due_amount = max(0, ($invoice->amount ?? 0) - ($invoice->advance_payment ?? 0) - $paid);
                $invoice->save();
            }
        }
        if ($jobCardId) {
            $job = \App\Models\JobCard::find($jobCardId);
            if ($job) {
                $advanceSum = Payment::where('job_card_id', $job->id)->where('status','paid')->sum('amount');
                // Do not mutate stored advance_payment after invoicing; only adjust status automatically
                if ($job->status !== 'done') {
                    $job->status = $advanceSum > 0 ? 'in_progress' : 'open';
                    $job->save();
                }
            }
        }
    }

    private function authorizeAccess(Request $request, Payment $payment): void
    {
        abort_if($payment->user_id !== $request->user()->id, 403);
    }

    public function summary(Request $request)
    {
        $user = $request->user();
        $now = Carbon::now();
        $today = $now->toDateString();

        $base = Payment::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid');

        $todayTotal = (clone $base)
            ->whereDate(DB::raw('COALESCE(paid_at, created_at)'), $today)
            ->sum('amount');

        $monthTotal = (clone $base)
            ->whereYear(DB::raw('COALESCE(paid_at, created_at)'), (int)$now->year)
            ->whereMonth(DB::raw('COALESCE(paid_at, created_at)'), (int)$now->month)
            ->sum('amount');

        return response()->json([
            'today_total' => (float)$todayTotal,
            'month_total' => (float)$monthTotal,
            'currency' => $user->currency,
        ]);
    }

    public function timeseries(Request $request)
    {
        $user = $request->user();
        $now = Carbon::now();
        $start = $now->copy()->subDays(29)->startOfDay();

        $rows = Payment::query()
            ->selectRaw("DATE(COALESCE(paid_at, created_at)) as d, SUM(amount) as total")
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereDate(DB::raw('COALESCE(paid_at, created_at)'), '>=', $start->toDateString())
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->pluck('total','d');

        $labels = [];
        $data = [];
        for ($i = 0; $i < 30; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $labels[] = $date;
            $data[] = (float)($rows[$date] ?? 0);
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data,
            'currency' => $user->currency,
        ]);
    }

    public function pdf(Request $request, Payment $payment)
    {
        $this->authorizeAccess($request, $payment);
        $payment->load(['customer','booking','invoice.user','jobCard.user']);
        $brand = $payment->jobCard?->user ?? $payment->invoice?->user ?? $request->user();
        $html = view('pdf.payment', ['payment' => $payment, 'brand' => $brand])->render();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $name = 'payment-'.($payment->id).'.pdf';
        return $pdf->download($name);
    }
}
