@extends('errors::minimal')

@section('title', __('405 Method Not Allowed'))
@section('code', '405')
@section('message', __('The method is not allowed for this route.'))
