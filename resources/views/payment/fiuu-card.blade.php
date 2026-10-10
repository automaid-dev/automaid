<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Card payment</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 48px 24px; text-align: center; color: #333; }
        .spinner { width: 36px; height: 36px; margin: 0 auto 16px; border: 4px solid #e3e8ef; border-top-color: #1565C0; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        button { margin-top: 20px; padding: 12px 24px; font-size: 16px; border: 0; border-radius: 8px; background: #1565C0; color: #fff; }
        small { color: #777; }
    </style>
</head>
<body>
    <div class="spinner"></div>
    <p>Opening secure card payment (RM{{ $amount }})…</p>
    <small>Powered by Fiuu. Your card details are entered on Fiuu's page, not stored by AutoMaid.</small>

    <form id="fiuu-card" method="POST" action="{{ $action }}">
        @foreach ($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <noscript><button type="submit">Continue to card payment</button></noscript>
    </form>

    <script>document.getElementById('fiuu-card').submit();</script>
</body>
</html>
