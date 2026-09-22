<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt — Order #{{ $order->id }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 32px 16px;
            color: #111827;
        }
        .card {
            max-width: 480px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 32px;
        }
        .letterhead { text-align: center; margin-bottom: 12px; }
        .letterhead .name { font-weight: 700; font-size: 15px; }
        .letterhead .line { font-size: 11px; color: #6b7280; margin-top: 2px; }
        .icon { text-align: center; font-size: 40px; color: #2563eb; margin-top: 8px; }
        .title { text-align: center; font-size: 18px; font-weight: 700; margin-top: 8px; }
        .pill-wrap { text-align: center; margin-top: 8px; }
        .pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background-color: #dcfce7;
            color: #16a34a;
        }
        hr { border: none; border-top: 1px solid #e5e7eb; margin: 24px 0; }
        .row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 14px; }
        .row .label { color: #6b7280; }
        .row.emphasize { font-weight: 700; font-size: 16px; }
        .row.emphasize .label { color: #111827; }
        .print-btn {
            display: block;
            margin: 20px auto 0;
            padding: 8px 20px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
        }
        @media print { .print-btn { display: none; } body { background: #fff; padding: 0; } .card { box-shadow: none; } }
    </style>
</head>
<body>
    <div class="card">
        @if ($setting && ($setting->company_name || $setting->company_address || $setting->company_phone || $setting->company_email))
            <div class="letterhead">
                @if ($setting->company_name)
                    <div class="name">{{ $setting->company_name }}</div>
                @endif
                @if ($setting->company_address)
                    <div class="line">{{ $setting->company_address }}</div>
                @endif
                @if ($setting->company_phone || $setting->company_email)
                    <div class="line">{{ collect([$setting->company_phone, $setting->company_email])->filter()->join('  ·  ') }}</div>
                @endif
            </div>
        @endif

        <div class="icon">&#128179;</div>
        <div class="title">Booking receipt</div>

        {{-- Same check as the app: only present once OrderComplete exists --}}
        @if ($isDelivered)
            <div class="pill-wrap"><span class="pill">Delivered</span></div>
        @endif

        <hr>

        <div class="row"><span class="label">Order #</span><span>{{ $order->id ?? '-' }}</span></div>
        @if ($order->series_no)
            <div class="row"><span class="label">Reference</span><span>{{ $order->series_no }}</span></div>
        @endif
        @if ($booking?->pickup_date)
            <div class="row"><span class="label">Pickup date</span><span>{{ $booking->pickup_date }}</span></div>
        @endif
        @if ($booking?->pickup_bag_quantity)
            <div class="row"><span class="label">Bag quantity</span><span>{{ $booking->pickup_bag_quantity }}</span></div>
        @endif
        <div class="row"><span class="label">Status</span><span>{{ $order->status ?? '-' }}</span></div>

        <hr>

        @if ($booking?->washing_charge)
            <div class="row"><span class="label">Washing charge</span><span>RM{{ $booking->washing_charge }}</span></div>
        @endif
        @if ($booking?->addon_charge && (float) $booking->addon_charge > 0)
            <div class="row"><span class="label">Add-on charge</span><span>RM{{ $booking->addon_charge }}</span></div>
        @endif
        @if ($booking?->delivery_charge)
            <div class="row"><span class="label">Delivery charge</span><span>RM{{ $booking->delivery_charge }}</span></div>
        @endif
        @if ($booking?->discount && (float) $booking->discount > 0)
            <div class="row"><span class="label">Discount</span><span>-RM{{ $booking->discount }}</span></div>
        @endif
        @if ((float) ($order->birthday_reward ?? 0) > 0)
            <div class="row"><span class="label">Birthday reward</span><span>-RM{{ number_format($order->birthday_reward, 2) }}</span></div>
        @endif
        @if ((float) ($order->insurance_fee ?? 0) > 0)
            <div class="row"><span class="label">Risk-Free Insurance</span><span>RM{{ number_format($order->insurance_fee, 2) }}</span></div>
        @endif
        @if ($booking?->tax)
            <div class="row"><span class="label">SST</span><span>RM{{ $booking->tax }}</span></div>
        @endif

        <hr>

        <div class="row emphasize">
            <span class="label">Grand total</span>
            <span>RM{{ $order->grand_total ?? $booking?->grand_total ?? '0.00' }}</span>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
</body>
</html>
