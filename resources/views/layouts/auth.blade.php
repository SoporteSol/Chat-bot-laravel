<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    @yield('styles')
</head>
<body>
    <div class="auth-box">
        {{-- <a href="{{ url('/') }}" class="auth-brand">
            <span class="brand-dot"></span>
            <span>Prime &lt;T&gt;</span>
        </a> --}}

        <div class="auth-card">
            @yield('content')
        </div>

        @yield('bottom')
    </div>

    <script src="{{ asset('js/auth.js') }}"></script>
    @yield('scripts')
</body>
</html>
