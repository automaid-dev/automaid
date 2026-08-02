@extends('emails.layouts.app')

@section('title', 'Order Completed')

@section('content')

    <p>Dear <strong>{{ $name }}</strong>,</p>

    <p>
        Congratulations! Your Order ID: {{ $order_id }} has been delivered by our Rider. Please check your laundry bag(s) to make sure none of your items is missing.
    </p>

    <p>
        If you have any issues or enquries, please do not hesitate to contact our customer support via the mobile app. We are happy to assist you. 
    </p>

@endsection
