@extends('errors.layout')

@section('code', '403')
@section('title', 'Access denied')
@section('message', "You don't have permission to access this page.")

@section('actions')
    <a href="/" class="btn btn-primary">Go to homepage</a>
    <button onclick="history.back()" class="btn btn-secondary">Go back</button>
@endsection
