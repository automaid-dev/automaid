<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Auto Maid')</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            font-size: 16px;
            line-height: 1.6;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }

        .email-container {
            margin: 40px;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        }

        .email-header {
            text-align: left;
            margin-bottom: 30px;
        }

        .email-logo {
            max-width: 180px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }

        th {
            background-color: #f1f1f1;
            text-align: left;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
        }

        td {
            vertical-align: top;
        }

        .total-row td {
            font-weight: bold;
            background-color: #f9f9f9;
        }

        .email-footer {
            margin-top: 40px;
            text-align: center;
            font-size: 14px;
            color: #999;
        }
        table.custom-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.custom-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        table.custom-table td.first-column {
            width: 25%;
            background-color: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <img src="{{ asset('assets/images/Logo_AutoMaid.png') }}" alt="Auto Maid Logo" class="email-logo">
        </div>

        {{-- Body Content --}}
        @yield('content')

        <p>
            Thank you,<br>
            <strong>Auto Maid Team</strong>
        </p>

        <div class="email-footer">
            &copy; {{ date('Y') }} Auto Maid. All rights reserved.
        </div>
    </div>
</body>
</html>
