<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>Payment Receipt #{{ $payment->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 20px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f5f5f5; }
        .right { text-align: right; }
        .muted { color: #666; font-size: 11px; }
    </style>
</head>
<body>
    @php
        $u = isset($brand) ? $brand : ($payment->jobCard->user ?? null);
        $currency = $payment->currency ?? ($u->currency ?? 'USD');
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
                    @if($u->address) <div class="muted">{{ $u->address }}</div> @endif
                    <div class="muted">
                        @if($u->phone) <span>Phone: {{ $u->phone }}</span> @endif
                        @if($u->email) <span style="margin-left:8px;">Email: {{ $u->email }}</span> @endif
                        @if($u->website) <span style="margin-left:8px;">Website: {{ $u->website }}</span> @endif
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <h1 style="margin-top:12px;">Payment Receipt</h1>
    <p><strong>Receipt #:</strong> {{ $payment->id }}</p>
    <p><strong>Customer:</strong> #{{ $payment->customer_id }} @if($payment->customer) {{ $payment->customer->name }} @endif</p>
    @if($payment->booking)
        <p><strong>Booking:</strong> #{{ $payment->booking->id }}</p>
    @endif
    @if($payment->jobCard)
        <p><strong>Job Card:</strong> #{{ $payment->jobCard->id }}</p>
    @endif
    <p><strong>Method:</strong> {{ $payment->method ?? 'n/a' }}</p>
    <p><strong>Reference:</strong> {{ $payment->reference ?? '-' }}</p>
    <p><strong>Paid At:</strong> {{ $payment->paid_at }}</p>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Advance / Confirmation Payment</td>
                <td class="right">{{ number_format($payment->amount, 2) }} {{ $currency }}</td>
            </tr>
        </tbody>
    </table>

    @if($payment->jobCard)
        <p class="muted">Due Amount after this payment: 
            @php
                $final = (float) ($payment->jobCard->confirmed_amount ?? 0);
                $paid = (float) \App\Models\Payment::where('job_card_id', $payment->jobCard->id)->where('status','paid')->sum('amount');
                $due = max(0, $final - $paid);
            @endphp
            {{ number_format($due, 2) }} {{ $currency }}
        </p>
    @endif

    <p class="muted" style="margin-top: 24px;">Generated on {{ now() }}</p>
</body>
</html>
