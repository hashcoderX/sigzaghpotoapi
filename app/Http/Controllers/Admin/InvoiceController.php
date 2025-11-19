<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Invoice::with(['customer','booking'])
            ->where('user_id', $user->id)
            ->orderByDesc('issued_at');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%$search%")
                  ->orWhere('status', 'like', "%$search%")
                  ->orWhere('amount', 'like', "%$search%");
            });
        }
        return $query->paginate((int) $request->query('per_page', 10));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'number' => ['nullable','string','max:100'],
            'customer_id' => ['required','integer','exists:customers,id'],
            'booking_id' => ['nullable','integer','exists:bookings,id'],
            'amount' => ['required','numeric','min:0'], // final amount after discount
            'discount' => ['nullable','numeric','min:0'],
            'advance_payment' => ['nullable','numeric','min:0'],
            'status' => ['nullable','string','in:draft,sent,paid,overdue'],
            'issued_at' => ['nullable','date'],
            'due_at' => ['nullable','date'],
            'items' => ['nullable','array'],
            'items.*.service' => ['required','string','max:255'],
            'items.*.qty' => ['required','integer','min:1'],
            'items.*.amount' => ['required','numeric','min:0'],
            'items.*.subamount' => ['required','numeric','min:0'],
        ]);
        Customer::where('id', $data['customer_id'])->where('user_id', $user->id)->firstOrFail();
        if (!empty($data['booking_id'])) {
            Booking::where('id', $data['booking_id'])->where('user_id', $user->id)->firstOrFail();
        }
        $data['user_id'] = $user->id;

        // Auto-generate invoice number if not provided
        if (empty($data['number'])) {
            $data['number'] = $this->generateInvoiceNumber();
        }
        $discount = $data['discount'] ?? 0;
        $advance = $data['advance_payment'] ?? 0;
        $dueAmount = max(0, ($data['amount']) - $advance);

        $invoice = DB::transaction(function () use ($data, $discount, $advance, $dueAmount) {
            return Invoice::create([
                'user_id' => $data['user_id'],
                'customer_id' => $data['customer_id'],
                'booking_id' => $data['booking_id'] ?? null,
                'number' => $data['number'],
                'amount' => $data['amount'],
                'status' => $data['status'] ?? 'draft',
                'issued_at' => $data['issued_at'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'discount' => $discount ?: null,
                'advance_payment' => $advance ?: null,
                'due_amount' => $dueAmount,
            ]);
        });
        if (!empty($data['items'])) {
            foreach ($data['items'] as $it) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'service' => $it['service'],
                    'qty' => $it['qty'],
                    'amount' => $it['amount'],
                    'sub_amount' => $it['subamount'],
                ]);
            }
        }
        return response()->json($invoice->load(['customer','booking','items']), 201);
    }

    private function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        // Try a few attempts to avoid collision
        for ($i = 0; $i < 5; $i++) {
            $rand = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $number = "INV-{$date}-{$rand}";
            if (!Invoice::where('number', $number)->exists()) {
                return $number;
            }
        }
        // Fallback with timestamp
        return 'INV-'.now()->format('YmdHis');
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->authorizeAccess($request, $invoice);
        return $invoice->load(['customer','booking','items']);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $this->authorizeAccess($request, $invoice);
        $data = $request->validate([
            'number' => ['sometimes','string','max:100'],
            'customer_id' => ['sometimes','integer','exists:customers,id'],
            'booking_id' => ['nullable','integer','exists:bookings,id'],
            'amount' => ['sometimes','numeric','min:0'],
            'status' => ['nullable','string','in:draft,sent,paid,overdue'],
            'issued_at' => ['nullable','date'],
            'due_at' => ['nullable','date'],
        ]);
        if (isset($data['customer_id'])) {
            Customer::where('id', $data['customer_id'])->where('user_id', $request->user()->id)->firstOrFail();
        }
        if (isset($data['booking_id'])) {
            Booking::where('id', $data['booking_id'])->where('user_id', $request->user()->id)->firstOrFail();
        }
        // Items update not implemented yet (could be separate endpoint)
        $invoice->update($data);
        return $invoice->load(['customer','booking','items']);
    }

    public function pdf(Request $request, Invoice $invoice)
    {
        $this->authorizeAccess($request, $invoice);
        $invoice->load(['customer','booking','items','user']);
        $brand = $invoice->user ?? $request->user();
        // Compute paid on this invoice to ensure accurate due in PDF
        $paidOnInvoice = \App\Models\Payment::where('invoice_id', $invoice->id)->where('status','paid')->sum('amount');
        $html = view('pdf.invoice', [
            'invoice' => $invoice,
            'brand' => $brand,
            'paidOnInvoice' => (float)$paidOnInvoice,
        ])->render();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        return $pdf->download(($invoice->number ?? 'invoice').'.pdf');
    }

    public function destroy(Request $request, Invoice $invoice)
    {
        $this->authorizeAccess($request, $invoice);
        $invoice->delete();
        return response()->json(['status' => 'deleted']);
    }

    private function authorizeAccess(Request $request, Invoice $invoice): void
    {
        abort_if($invoice->user_id !== $request->user()->id, 403);
    }
}
