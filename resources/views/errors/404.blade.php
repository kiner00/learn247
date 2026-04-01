@extends('errors.layout')

@section('code', '404')
@section('title', 'Page not found')
@section('message', "The page you're looking for doesn't exist or has been moved.")

@section('actions')
    <a href="/" class="btn btn-primary">Go to homepage</a>
    <button onclick="history.back()" class="btn btn-secondary">Go back</button>
@endsection
