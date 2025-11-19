<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>Job Card #{{ $job->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1, h3 { margin: 0 0 8px 0; }
        h1 { font-size: 20px; }
        h3 { font-size: 16px; }
        .muted { color: #666; font-size: 11px; }
        .section { margin-top: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f5f5f5; }
        .right { text-align: right; }
    </style>
</head>
<body>
    @php
        $user = $job->user;
        $currency = $user->currency ?? 'USD';
        $final = (float) ($job->confirmed_amount ?? 0);
        // Prefer sum of paid payments; fallback to advance_payment
        $paid = (float) \App\Models\Payment::where('job_card_id', $job->id)->where('status','paid')->sum('amount');
        if ($paid <= 0) { $paid = (float) ($job->advance_payment ?? 0); }
        $due = max(0, $final - $paid);
    @endphp

    <h3>{{ $user->name }}</h3>
    @if($user->address)
        <div>{{ $user->address }}</div>
    @endif
    <div>
        @if($user->phone) <span><strong>Phone:</strong> {{ $user->phone }}</span> @endif
        @if($user->whatsapp) <span style="margin-left:12px;"><strong>WhatsApp:</strong> {{ $user->whatsapp }}</span> @endif
    </div>
    <div>
        @if($user->website) <span><strong>Website:</strong> {{ $user->website }}</span> @endif
        @if($user->email) <span style="margin-left:12px;"><strong>Email:</strong> {{ $user->email }}</span> @endif
    </div>

    <div class="section">
        <h1>Job Card #{{ $job->id }}</h1>
        <div><strong>Title:</strong> {{ $job->title }}</div>
        @if($job->description)
            <div><strong>Description:</strong> {{ $job->description }}</div>
        @endif
        <div><strong>Status:</strong> {{ ucfirst(str_replace('_',' ', $job->status)) }}</div>
        @if($job->assigned_to)
            <div><strong>Assigned To:</strong> {{ $job->assigned_to }}</div>
        @endif
        @if($job->booking)
            <div><strong>Booking:</strong> #{{ $job->booking->id }} @if($job->booking->location) — {{ $job->booking->location }} @endif</div>
        @endif
    </div>

    @if($job->items && $job->items->count())
    <div class="section">
        <h3>Items</h3>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th class="right">Amount ({{ $currency }})</th>
                    <th class="right">Sub Amount ({{ $currency }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($job->items as $item)
                    <tr>
                        <td>{{ $item->service }}</td>
                        <td>{{ $item->qty }}</td>
                        <td class="right">{{ number_format($item->amount, 2) }}</td>
                        <td class="right">{{ number_format($item->sub_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="section">
        <table style="width:50%; margin-left:auto;">
            <tr>
                <td><strong>Final Amount:</strong></td>
                <td class="right">{{ number_format($final, 2) }} {{ $currency }}</td>
            </tr>
            <tr>
                <td><strong>Advance Amount:</strong></td>
                <td class="right">{{ number_format($paid, 2) }} {{ $currency }}</td>
            </tr>
            <tr>
                <td><strong>Due Amount:</strong></td>
                <td class="right">{{ number_format($due, 2) }} {{ $currency }}</td>
            </tr>
        </table>
    </div>

    <p class="muted" style="margin-top: 18px;">Generated on {{ now() }}</p>
</body>
</html>
