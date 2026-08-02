@extends('emails.layouts.app')

@section('title', 'Subscription Successful')

@section('content')

    <p>Dear <strong>{{ $name }}</strong>,</p>

    <p>Thank you for your subscription! Please refer to the invoice below for your subscription details.</p>

    <table class="custom-table">
        <tr>
            <td class="first-column"><strong>Invoice No</strong></td>
            <td>{{ $order->series_no ?? '' }}</td>
        </tr>
        <tr>
            <td class="first-column"><strong>Invoice Date</strong></td>
            <td>{{ $order->created_at->format('F j, Y') }}</td>            
        </tr>
        <tr>
            <td class="first-column"><strong>From</strong></td>
            <td>
                Paynwash Solutions Sdn Bhd
                <br>
                No 3-15d, Jalan Desa 2/2, Desa Aman Puri, 52100 Kuala Lumpur
                <br>
                60132921610 support@automaid.asia
            </td>
        </tr>
        <tr>
            <td class="first-column"><strong>Bill To</strong></td>
            <td>
                {{ $order->billing_name }}
                <br>
                {{ $order->billing_address_line_1 }} {{ $order->billing_address_line_2 }} {{ $order->billing_postcode }} {{ $order->billing_city }} {{ $order->billing_state_id ? get_state_name($order->billing_state_id)->name : '' }} {{ $order->billing_country_id ? get_country_name($order->billing_country_id)->name : '' }}
                <br>
                {{ $order->billing_phone }} {{ $order->billing_email }} 
            </td>
        </tr>
        <tr>
            <td class="first-column"><strong>Subscription Plan</strong></td>
            <td>Auto Maid Subscription</td>
        </tr>
        <tr>
            <td class="first-column"><strong>Start Date</strong></td>
            <td>{{ $order->subscription ? \Carbon\Carbon::parse($order->subscription->start_date)->format('F j, Y') : '' }}</td>
        </tr>
        <tr>
            <td class="first-column"><strong>Renew Date</strong></td>
            <td>{{ $order->subscription ? \Carbon\Carbon::parse($order->subscription->end_date)->format('F j, Y') : '' }}</td>            
        </tr>
        <tr>
            <td class="first-column"><strong>Total Amount (RM)</strong></td>
            <td>{{ number_format($order->grand_total, 2) }}</td>
        </tr>
        <tr>
            <td class="first-column"><strong>Payment Method</strong></td>
            <td>{{ $order->subscription ? $order->subscription->cc_brand . ' (**** **** **** ' . $order->subscription->cc_last_four . ')' : '' }}</td>                        
        </tr>
    </table>
    
    <p style="margin-top: 30px;">
        We’re excited to have you with us. If you have any questions or need support, feel free to reach out.
    </p>

@endsection
