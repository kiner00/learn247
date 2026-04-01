@extends('errors.layout')

@section('code', '500')
@section('title', 'Something went wrong')
@section('message', "We're working on getting this fixed. Please try again in a moment.")

@section('actions')
    <a href="/" class="btn btn-primary">Go to homepage</a>
    <button onclick="location.reload()" class="btn btn-secondary">Try again</button>
@endsection
