@extends('emails.layouts.app')

@section('title', 'Reset Your Password')

@section('content')

    <p>
        You're receiving this email because you requested a password reset for your Auto Maid account associated with <strong>{{ $email }}</strong>.
    </p>

    <p>
        If you didn’t request this, you can safely ignore this email — no changes will be made.
    </p>

    <p>
        The password link below is for the account with the email: <strong>{{ $email }}</strong>. To create a new password, simply click the button below
    </p>

    <p style="text-align: left; margin-top: 30px;">
        <a href="{{ $url }}" style="
            background-color: #007BFF;
            color: white;
            padding: 14px 28px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            display: inline-block;">
            Reset Password
        </a>
    </p>

    <p style="margin-top: 40px;">
        This link will expire in 60 minutes. If you continue to have issues, please contact our support team.
    </p>

@endsection
