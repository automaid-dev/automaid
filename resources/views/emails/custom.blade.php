@extends('emails.layouts.app')

@section('title', $subject ?? 'Auto Maid')

@section('content')
    {!! $body !!}
@endsection
