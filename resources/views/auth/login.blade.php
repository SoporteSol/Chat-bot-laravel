@extends('layouts.auth')

@section('content')
<h1 class="auth-title">Iniciar Sesión</h1>

@if (session('status'))
    <div class="alert-success">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="alert-error">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('login') }}">
    @csrf

    <div class="field">
        {{-- <label for="email" class="field-label">Acces</label> --}}
        <div class="input-wrap">
            <input 
                id="email" 
                type="email" 
                class="form-control @error('email') has-error @enderror" 
                name="email" 
                value="{{ old('email') }}" 
                placeholder="xd "
                required 
                autocomplete="email" 
                autofocus>
        </div>
        @error('email')
            <span class="field-error">{{ $message }}</span>
        @enderror
    </div>

    <div class="field">
        <div class="input-wrap">
            <input 
                id="password" 
                type="password" 
                class="form-control @error('password') has-error @enderror" 
                name="password" 
                placeholder="••••••••"
                required 
                autocomplete="current-password">
            <button type="button" class="toggle-pass" onclick="togglePass('password', this)" title="Mostrar u ocultar contraseña" aria-label="Mostrar u ocultar contraseña">
                <svg class="svg-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg class="svg-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
            </button>
        </div>
        @error('password')
            <span class="field-error">{{ $message }}</span>
        @enderror
    </div>


    <button type="submit" class="btn-submit">
        Acceder
    </button>
</form>
@endsection
