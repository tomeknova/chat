{{-- resources/views/layouts/layout.blade.php — header/footer wiring (mirrors KINGS5) --}}

@extends('layouts.master')

@section('header')
    @include('partials.header')
@endsection

@section('content')
    @yield('contentpages')
@endsection

@section('footer')
    @include('partials.footer')
@endsection
