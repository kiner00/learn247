@extends('errors.layout')

@section('code', '503')
@section('title', 'Under maintenance')
@section('message', "We're doing some quick maintenance. We'll be back shortly.")

@section('actions')
    <button onclick="location.reload()" class="btn btn-primary">Try again</button>
@endsection
