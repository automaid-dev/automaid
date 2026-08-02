@extends('emails.layouts.app')

@section('title', 'Welcome to Auto Maid')

@section('content')
    
    <p>Dear <strong>{{ $name }}</strong>,</p>

    <p>Welcome to <strong>Auto Maid</strong>! Thank you for signing up with us. You are now a part of the leading pickup laundry in Malaysia.</p>

    <p>Your new account has been setup and you can now login to our customer app using the details below:</p>

    <ul>
        <li>Registered email address: <strong>{{ $email }}</strong></li>
    </ul>

    <p>We’re excited to have you on board! Whether it’s scheduling pickups, tracking your orders, or managing your preferences, everything you need is right at your fingertips — quick, simple, and hassle-free.</p>

    <p>Just click on the mobile application & login, and it's as easy as 1, 2, 3!</p>
    
@endsection
