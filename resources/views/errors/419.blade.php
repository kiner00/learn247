@extends('errors.layout')

@section('code', '419')
@section('title', 'Session expired')
@section('message', 'Your session has expired. Please refresh the page and try again.')

@section('actions')
    <button onclick="location.reload()" class="btn btn-primary">Refresh page</button>
@endsection
