@extends('emails.layouts.app')

@section('title', 'Reject User')

@section('content')

<p>Dear <strong>{{ $name }}</strong>,</p>

<p>
    We are sorry to inform you that your application has been rejected.
</p>

<table width="100%" cellpadding="0" cellspacing="0" 
       style="border-collapse: collapse; margin: 20px 0;">
    <tr>
        <td width="30%" 
            style="border:1px solid #e5e7eb; padding:12px; font-weight:bold; background:#f9fafb;">
            Rejection Reason
        </td>
        <td width="70%" 
            style="border:1px solid #e5e7eb; padding:12px;">
            {{ $reason ?? '-' }}
        </td>
    </tr>
</table>

<p>
    If this is a mistake or if you have any inquiries, please do not hesitate
    to contact our customer support via the mobile app. We are happy to assist you.
</p>

@endsection
