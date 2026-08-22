<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $success ? 'Payment successful' : 'Payment failed' }}</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f6fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 40px 32px;
            max-width: 360px;
            width: 90%;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
        }
        .icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: #fff;
        }
        .icon.success { background: #2e7d32; }
        .icon.failure { background: #c62828; }
        h1 {
            font-size: 20px;
            margin: 0 0 8px;
            color: #1a1a1a;
        }
        p {
            font-size: 14px;
            color: #666;
            margin: 0;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon {{ $success ? 'success' : 'failure' }}">{{ $success ? '✓' : '✕' }}</div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
    </div>
</body>
</html>
