@extends('emails.layouts.app')

@section('title', 'New Reply from Customer Support')

@section('content')

    <p>Dear <strong>{{ $name }}</strong>,</p>

    <p>
        You have new reply from Customer Support. Please login your app to view your messages.
    </p>

    <table class="custom-table">
        <tr>
            <td class="first-column"><strong>Order ID</strong></td>
            <td>{{ $ticket->order->series_no ?? '' }}</td>
        </tr>
        <tr>
            <td class="first-column"><strong>Issue Type</strong></td>
            <td>{{ $ticket->issue_type }}</td>            
        </tr>
    </table>

@endsection