@extends('emails.layouts.app')

@section('title', 'Subscription Cancelled')

@section('content')

    <p>Dear <strong>{{ $name }}</strong>,</p>

    <p>
        We’re sorry to hear that you have cancelled your subscription.
    </p>

    <p>
        Should you plan to continue your subscription, please login to the Auto Maid app and resubscribe to the Auto Maid plan again.
    </p>

@endsection
