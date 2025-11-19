<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 20px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f5f5f5; }
        .totals { margin-top: 15px; width: 100%; }
        .totals td { border: none; }
        .right { text-align: right; }
    </style>
</head>
<body>
    @php
        $u = isset($brand) ? $brand : ($invoice->user ?? null);
        $currency = $u->currency ?? 'USD';
        $logo = $u && $u->logo_path ? asset('storage/'.$u->logo_path) : null;
    @endphp
    <table style="width:100%; border:0; border-collapse:separate;">
        <tr>
            <td style="border:0; vertical-align:middle;">
                @if($logo)
                    <img src="{{ $logo }}" alt="Logo" style="height:48px;" />
                @endif
            </td>
            <td style="border:0; text-align:right;">
                @if($u)
                    <div style="font-weight:bold; font-size:14px;">{{ $u->name }}</div>
                    @if($u->address) <div style="color:#666; font-size:11px;">{{ $u->address }}</div> @endif
                    <div style="color:#666; font-size:11px;">
                        @if($u->phone) <span>Phone: {{ $u->phone }}</span> @endif
                        @if($u->email) <span style="margin-left:8px;">Email: {{ $u->email }}</span> @endif
                        @if($u->website) <span style="margin-left:8px;">Website: {{ $u->website }}</span> @endif
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <h1 style="margin-top:12px;">Invoice {{ $invoice->number }}</h1>
    <p><strong>Customer:</strong> #{{ $invoice->customer_id }} @if($invoice->customer) {{ $invoice->customer->name }} @endif</p>
    @if($invoice->booking)
        <p><strong>Booking:</strong> #{{ $invoice->booking->id }}</p>
    @endif
    <p><strong>Status:</strong> {{ ucfirst($invoice->status) }}</p>
    <p><strong>Issued At:</strong> {{ $invoice->issued_at }} | <strong>Due At:</strong> {{ $invoice->due_at }}</p>

    @if($invoice->items && $invoice->items->count())
    <table>
        <thead>
            <tr>
                <th>Service</th>
                <th>Qty</th>
                <th>Amount ({{ $currency }})</th>
                <th>Sub Amount ({{ $currency }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->service }}</td>
                    <td>{{ $item->qty }}</td>
                    <td>{{ number_format($item->amount, 2) }}</td>
                    <td>{{ number_format($item->sub_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <table class="totals">
        @php
            $paidOnInv = isset($paidOnInvoice) ? (float)$paidOnInvoice : 0.0;
            $computedDue = max(0, (float)($invoice->amount ?? 0) - (float)($invoice->advance_payment ?? 0) - $paidOnInv);
            $dueToShow = isset($paidOnInvoice)
                ? $computedDue
                : (float)($invoice->due_amount ?? (($invoice->amount ?? 0) - ($invoice->advance_payment ?? 0)));
        @endphp
        <tr>
            <td class="right"><strong>Discount:</strong></td>
            <td class="right">{{ number_format($invoice->discount ?? 0, 2) }} {{ $currency }}</td>
        </tr>
        <tr>
            <td class="right"><strong>Advance Paid (Job Card):</strong></td>
            <td class="right">{{ number_format($invoice->advance_payment ?? 0, 2) }} {{ $currency }}</td>
        </tr>
        <tr>
            <td class="right"><strong>Total Amount:</strong></td>
            <td class="right">{{ number_format($invoice->amount, 2) }} {{ $currency }}</td>
        </tr>
        <tr>
            <td class="right"><strong>Due Amount:</strong></td>
            <td class="right">{{ number_format($dueToShow, 2) }} {{ $currency }}</td>
        </tr>
    </table>

    <p style="margin-top:30px; font-size:10px; color:#666;">Generated on {{ now() }}</p>
</body>
</html>
