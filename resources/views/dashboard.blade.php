@extends('statisty::layout')

@section('content')
    <h1>Statisty Dashboard</h1>

    <div class="statisty-row">
        @yield('kpis')
    </div>

    <div class="statisty-row">
        @yield('charts')
    </div>

    <div class="statisty-row">
        @yield('tables')
    </div>
@endsection
