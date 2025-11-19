<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Mock Photo Payment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            color-scheme: light dark;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            background: #f8fafc;
            color: #0f172a;
        }
        .wrapper {
            max-width: 520px;
            margin: 4rem auto;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
            padding: 2.5rem;
        }
        h1 {
            font-size: 1.65rem;
            margin-bottom: 0.75rem;
        }
        .meta {
            margin-bottom: 1.75rem;
            padding: 1rem 1.25rem;
            background: #f1f5f9;
            border-radius: 12px;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .meta div {
            margin-bottom: 0.35rem;
        }
        .meta div:last-child {
            margin-bottom: 0;
        }
        .actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .actions a,
        .actions button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 0.85rem 1.1rem;
            text-decoration: none;
            border-radius: 9999px;
            font-weight: 600;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            cursor: pointer;
            border: none;
        }
        .primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #ffffff;
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.35);
        }
        .primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 36px rgba(99, 102, 241, 0.4);
        }
        .secondary {
            background: #e2e8f0;
            color: #1e293b;
        }
        .secondary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.15);
        }
        .notice {
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h1>Mock Checkout</h1>
        <p>This page simulates a hosted payment experience for local testing.</p>
        <div class="meta">
            @if($photoId)
                <div><strong>Photo ID:</strong> {{ $photoId }}</div>
            @endif
            @if($intentId)
                <div><strong>Payment Intent:</strong> {{ $intentId }}</div>
            @endif
            @if($amount !== null)
                <div><strong>Amount:</strong> {{ number_format((float) $amount, 2) }} $</div>
            @endif
        </div>
        <div class="actions">
            @if($returnUrl)
                <a class="primary" href="{{ $returnUrl }}">Complete Payment</a>
            @else
                <button class="primary" type="button" onclick="alert('No return URL supplied to redirect back to your site.');">Complete Payment</button>
            @endif
            @if($cancelUrl)
                <a class="secondary" href="{{ $cancelUrl }}">Cancel Payment</a>
            @else
                <button class="secondary" type="button" onclick="window.history.back();">Cancel Payment</button>
            @endif
        </div>
        <p class="notice">Update the checkout controller once your real payment gateway is ready so this mock endpoint is no longer needed.</p>
    </div>
</body>
</html>
