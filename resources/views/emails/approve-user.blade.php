@extends('emails.layouts.app')

@section('title', 'Approve User')

@section('content')
    
    <p>Dear <strong>{{ $name }}</strong>,</p>

    <p>Congratulations, your application has been approved! Please login to your app now and start taking orders now.</p>

    <p>If you have any issues or enquries, please do not hesitate to contact our customer support via the mobile app. We are happy to assist you.</p>
    
@endsection
