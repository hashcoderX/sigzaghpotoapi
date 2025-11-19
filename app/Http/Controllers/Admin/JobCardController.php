<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\JobCard;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class JobCardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = JobCard::with(['booking','items'])
            ->where('user_id', $user->id)
            ->orderByDesc('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('q')) {
            $query->where(function ($q2) use ($search) {
                $q2->where('title', 'like', "%$search%")
                   ->orWhere('status', 'like', "%$search%")
                   ->orWhere('assigned_to', 'like', "%$search%");
            });
        }
        $result = $query->paginate((int) $request->query('per_page', 10));
        // Append total_paid (sum of paid payments tied to this job card)
        $result->getCollection()->transform(function ($job) {
            $paid = \App\Models\Payment::where('job_card_id', $job->id)->where('status','paid')->sum('amount');
            $job->total_paid = (float) $paid;
            return $job;
        });
        return $result;
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'booking_id' => ['required','integer','exists:bookings,id'],
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'status' => ['nullable','string','in:open,in_progress,done'],
            'assigned_to' => ['nullable','string','max:255'],
            'due_date' => ['nullable','date'],
            'confirmed_amount' => ['nullable','numeric','min:0'],
            'advance_payment' => ['nullable','numeric','min:0'],
            'discount' => ['nullable','numeric','min:0'],
            'items' => ['nullable','array'],
            'items.*.service' => ['required','string','max:255'],
            'items.*.qty' => ['required','integer','min:1'],
            'items.*.amount' => ['required','numeric','min:0'],
            'items.*.subamount' => ['required','numeric','min:0'],
        ]);
        Booking::where('id', $data['booking_id'])->where('user_id', $user->id)->firstOrFail();
        $data['user_id'] = $user->id;
        // Compute confirmed_amount from items - discount if items provided
        if (!empty($data['items'])) {
            $subtotal = 0;
            foreach ($data['items'] as $it) { $subtotal += $it['subamount']; }
            $discount = $data['discount'] ?? 0;
            $data['confirmed_amount'] = max(0, $subtotal - $discount);
        }
        // Auto status based on advance payment if not explicitly provided
        if (empty($data['status'])) {
            $adv = (float)($data['advance_payment'] ?? 0);
            $data['status'] = $adv > 0 ? 'in_progress' : 'open';
        }
        $job = JobCard::create($data);
        if (!empty($data['items'])) {
            foreach ($data['items'] as $it) {
                \App\Models\JobCardItem::create([
                    'job_card_id' => $job->id,
                    'service' => $it['service'],
                    'qty' => $it['qty'],
                    'amount' => $it['amount'],
                    'sub_amount' => $it['subamount'],
                ]);
            }
        }
        // If an advance was specified on creation, also record a Payment for it (to keep totals consistent)
        if ((float)($job->advance_payment ?? 0) > 0) {
            \App\Models\Payment::create([
                'user_id' => $user->id,
                'customer_id' => $job->booking->customer_id ?? null,
                'booking_id' => $job->booking_id,
                'job_card_id' => $job->id,
                'amount' => (float)$job->advance_payment,
                'currency' => $user->currency ?? 'USD',
                'method' => 'advance',
                'reference' => 'job-card-advance',
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }
        return response()->json($job->load(['booking','items','user']), 201);
    }

    public function show(Request $request, JobCard $job_card)
    {
        $this->authorizeAccess($request, $job_card);
        return $job_card->load('booking');
    }

    public function update(Request $request, JobCard $job_card)
    {
        $this->authorizeAccess($request, $job_card);
        $oldAdvance = (float)($job_card->advance_payment ?? 0);
        $data = $request->validate([
            'booking_id' => ['sometimes','integer','exists:bookings,id'],
            'title' => ['sometimes','string','max:255'],
            'description' => ['nullable','string'],
            'status' => ['nullable','string','in:open,in_progress,done'],
            'assigned_to' => ['nullable','string','max:255'],
            'due_date' => ['nullable','date'],
            'confirmed_amount' => ['sometimes','numeric','min:0'],
            'advance_payment' => ['sometimes','numeric','min:0'],
            'discount' => ['sometimes','numeric','min:0'],
            'items' => ['nullable','array'],
            'items.*.service' => ['required','string','max:255'],
            'items.*.qty' => ['required','integer','min:1'],
            'items.*.amount' => ['required','numeric','min:0'],
            'items.*.subamount' => ['required','numeric','min:0'],
        ]);
        if (isset($data['booking_id'])) {
            Booking::where('id', $data['booking_id'])->where('user_id', $request->user()->id)->firstOrFail();
        }
        // If items provided, replace and recompute confirmed amount
        if (!empty($data['items'])) {
            $subtotal = 0;
            foreach ($data['items'] as $it) { $subtotal += $it['subamount']; }
            $discount = $data['discount'] ?? ($job_card->discount ?? 0);
            $data['confirmed_amount'] = max(0, $subtotal - $discount);
        }
        // Auto status update if not done and advance payment changed
        if ($job_card->status !== 'done' && array_key_exists('advance_payment', $data)) {
            $adv = (float)($data['advance_payment'] ?? 0);
            $data['status'] = $adv > 0 ? 'in_progress' : 'open';
        }
        $job_card->update($data);
        if (!empty($data['items'])) {
            $job_card->items()->delete();
            foreach ($data['items'] as $it) {
                \App\Models\JobCardItem::create([
                    'job_card_id' => $job_card->id,
                    'service' => $it['service'],
                    'qty' => $it['qty'],
                    'amount' => $it['amount'],
                    'sub_amount' => $it['subamount'],
                ]);
            }
        }
        // If advance is newly added (previously 0), create a Payment for it (avoid duplicates if payments already exist)
        if (array_key_exists('advance_payment', $data)) {
            $newAdvance = (float)($job_card->advance_payment ?? 0);
            if ($oldAdvance <= 0 && $newAdvance > 0) {
                $existingPaid = \App\Models\Payment::where('job_card_id', $job_card->id)->where('status','paid')->exists();
                if (!$existingPaid) {
                    \App\Models\Payment::create([
                        'user_id' => $request->user()->id,
                        'customer_id' => $job_card->booking->customer_id ?? null,
                        'booking_id' => $job_card->booking_id,
                        'job_card_id' => $job_card->id,
                        'amount' => $newAdvance,
                        'currency' => $request->user()->currency ?? 'USD',
                        'method' => 'advance',
                        'reference' => 'job-card-advance',
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);
                }
            }
        }
        return $job_card->load(['booking','items','user']);
    }

    public function destroy(Request $request, JobCard $job_card)
    {
        $this->authorizeAccess($request, $job_card);
        $job_card->delete();
        return response()->json(['status' => 'deleted']);
    }

    private function authorizeAccess(Request $request, JobCard $job): void
    {
        abort_if($job->user_id !== $request->user()->id, 403);
    }

    public function pdf(Request $request, JobCard $job_card)
    {
        $this->authorizeAccess($request, $job_card);
        $job_card->load(['booking', 'items', 'user']);
        $pdf = Pdf::loadView('pdf.job_card', ['job' => $job_card]);
        return $pdf->download('job-card-' . $job_card->id . '.pdf');
    }

    public function createInvoice(Request $request, JobCard $job_card)
    {
        $this->authorizeAccess($request, $job_card);
        $user = $request->user();
        $job_card->load(['items','booking']);
        $data = $request->validate([
            // Require a positive collection amount for an invoice payment period
            'collect_amount' => ['required','numeric','gt:0'],
            'currency' => ['nullable','string','size:3'],
            'method' => ['nullable','string','max:100'],
            'reference' => ['nullable','string','max:255'],
        ]);
        $subtotal = 0;
        foreach (($job_card->items ?? []) as $it) { $subtotal += (float) $it->sub_amount; }
        $discount = (float) ($job_card->discount ?? 0);
        $amount = max(0, $subtotal - $discount);

        // Job card advances already paid reduce the starting invoice due
        $jobAdvancePaid = (float) Payment::where('job_card_id', $job_card->id)->where('status','paid')->sum('amount');
        // Some users may set advance on the job card without recording a Payment; honor that too.
        $jobAdvanceField = (float) ($job_card->advance_payment ?? 0);
        $jobAdvanceEffective = max($jobAdvancePaid, $jobAdvanceField);
        $currentDue = max(0, $amount - $jobAdvanceEffective);

        // Validate collection amount does not exceed current due
        $col = (float)$data['collect_amount'];
        if ($col <= 0) {
            return response()->json(['message' => 'collect_amount must be greater than zero'], 422);
        }
        if ($col > $currentDue) {
            return response()->json(['message' => 'collect_amount cannot exceed current due'], 422);
        }

        // Generate invoice number
        $number = $this->generateInvoiceNumber();

        $invoice = DB::transaction(function () use ($user, $job_card, $amount, $discount, $number, $jobAdvanceEffective) {
            $inv = Invoice::create([
                'user_id' => $user->id,
                'customer_id' => $job_card->booking->customer_id ?? null,
                'booking_id' => $job_card->booking_id,
                'number' => $number,
                'amount' => $amount,
                'status' => 'sent',
                'issued_at' => now(),
                'discount' => $discount ?: null,
                'advance_payment' => $jobAdvanceEffective,
                'due_amount' => max(0, $amount - $jobAdvanceEffective),
            ]);
            foreach (($job_card->items ?? []) as $jit) {
                InvoiceItem::create([
                    'invoice_id' => $inv->id,
                    'service' => $jit->service,
                    'qty' => $jit->qty,
                    'amount' => $jit->amount,
                    'sub_amount' => $jit->sub_amount,
                ]);
            }
            return $inv;
        });

        // Optional immediate collection (advance on invoice)
        if ($col > 0) {
            $pay = Payment::create([
                'user_id' => $user->id,
                'customer_id' => $invoice->customer_id,
                'booking_id' => $invoice->booking_id,
                'invoice_id' => $invoice->id,
                'job_card_id' => $job_card->id,
                'amount' => $col,
                'currency' => $data['currency'] ?? ($user->currency ?? 'USD'),
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            // Update invoice due
            $paid = Payment::where('invoice_id', $invoice->id)->where('status','paid')->sum('amount');
            $invoice->due_amount = max(0, $amount - ($invoice->advance_payment ?? 0) - $paid);
            $invoice->save();

            // Update job card status (do not mutate advance_payment after invoicing)
            $advanceSum = Payment::where('job_card_id', $job_card->id)->where('status','paid')->sum('amount');
            if ($job_card->status !== 'done') {
                $job_card->status = $advanceSum > 0 ? 'in_progress' : 'open';
                $job_card->save();
            }
        }
        return response()->json($invoice->load(['customer','booking','items']), 201);
    }

    private function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        for ($i = 0; $i < 5; $i++) {
            $rand = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $number = "INV-{$date}-{$rand}";
            if (!Invoice::where('number', $number)->exists()) {
                return $number;
            }
        }
        return 'INV-'.now()->format('YmdHis');
    }
}
