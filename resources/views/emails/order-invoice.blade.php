@extends('emails.layouts.app')

@section('title', 'Order Invoice')

@section('content')

    <p>Dear <strong>{{ $name }}</strong>,</p>

    <p>
        Thank you for your recent order. Please refer to the invoice below for your purchase information.
    </p>

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
                {{ $order->billing_name ?? '' }}
                <br>
                {{ $order->billing_address_line_1 ?? '' }} {{ $order->billing_address_line_2 ?? '' }} {{ $order->billing_postcode ?? '' }} {{ $order->billing_city ?? '' }} {{ $order->billing_state ? get_state_name($order->billing_state)->name : '' }} {{ $order->billing_country ? get_country_name($order->billing_country)->name : '' }}
                <br>
                {{ $order->billing_phone ?? '' }} {{ $order->billing_email ?? '' }} 
            </td>
        </tr>
    </table>

    <table class="custom-table">
        <tr>
            <td class="first-column"><strong>Order ID</strong></td>
            <td>{{ $order->id }}</td>            
        </tr>
        <tr>
            <td class="first-column"><strong>Pickup Date</strong></td>
            <td>{{ $order->booking->pickup_date ?? '' }}</td>
        </tr>
        <tr>
            <td class="first-column"><strong>Bag Quantity</strong></td>
            <td>{{ $order->booking->pickup_bag_quantity ?? '' }}</td>
        </tr>
        <tr>
            <td class="first-column"><strong>Add-ons</strong></td>
            <td>
                @if ($order->order_addons && count($order->order_addons))
                    @foreach ($order->order_addons as $order_addon)
                        {{ $order_addon->addon->title }}@if (!$loop->last), @endif
                    @endforeach
                @else
                    {{ '-' }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="first-column"><strong>Location</strong></td>
            <td>
                {{ $order->booking->pickup_location->address_line_1 ?? '' }} {{ $order->booking->pickup_location->address_line_2 ?? '' }} {{ $order->booking->pickup_location->postcode ?? '' }} {{ $order->booking->pickup_location->city ?? '' }} {{ $order->booking->pickup_location->state_id ? get_state_name($order->booking->pickup_location->state_id)->name : '' }} {{ $order->booking->pickup_location->country_id ? get_country_name($order->booking->pickup_location->country_id)->name : '' }}
            </td>
        </tr>
    </table>

    <table class="custom-table">
        <tr>
            <td class="first-column"><strong>Washing Charge (RM)</strong></td>
            <td>{{ number_format($order->sub_total, 2) }}</td>
        </tr>

        @if ($order->order_addons && count($order->order_addons))
            @foreach ($order->order_addons as $order_addon)
                <tr>
                    <td class="first-column"><strong>{{ $order_addon->addon->title }} (RM)</strong></td>
                    <td>{{ number_format($order_addon->addon->price, 2) }}</td>
                </tr>
            @endforeach
        @endif

        <tr>
            <td class="first-column"><strong>Add-ons Discount (RM)</strong></td>
            <td>-{{ number_format($order->addon_discount, 2) }}</td>
        </tr>
        <tr>
            <td class="first-column"><strong>Discount (RM)</strong></td>
            <td>{{ number_format($order->discount, 2) }}</td>
        </tr>
        <tr>
            <td class="first-column"><strong>Birthday Reward (RM)</strong></td>
            <td>{{ number_format($order->birthday_reward, 2) }}</td>
        </tr>
        <tr>
            <td class="first-column"><strong>Risk-Free Insurance (RM)</strong></td>
            <td>{{ number_format($order->insurance_fee, 2) }}</td>
        </tr>
        <tr>
            <td class="first-column"><strong>Delivery Charges (RM)</strong></td>
            <td>{{ number_format($order->sub_total, 2) }}</td>
        </tr>
        <tr>
            <td class="first-column"><strong>SST (8%) (RM)</strong></td>
            <td>{{ number_format($order->tax_total, 2) }}</td>
        </tr>
        <tr>
            <td class="first-column"><strong>Total (RM)</strong></td>
            <td>{{ number_format($order->grand_total, 2) }}</td>
        </tr>
    </table>

    <p style="margin-top: 30px;">
        If you have any questions, feel free to contact our support team.
    </p>

@endsection
