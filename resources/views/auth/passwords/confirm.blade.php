@extends('layouts.auth')

@section('title', 'Confirmar Contraseña')

@section('nav_switch')
    <a href="{{ route('login') }}" class="nav-switch-link">
        ¿Volver? <strong>Iniciar Sesión</strong>
    </a>
@endsection

@section('content')
    <div class="form-header">
        <div class="form-category">Zona segura</div>
        <h2 class="form-headline">Confirmar Clave.</h2>
        <p class="form-subline">Por favor confirma tu contraseña antes de continuar a esta sección restringida.</p>
    </div>

    @if ($errors->any())
        <div class="banner-alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.confirm') }}" novalidate>
        @csrf

        <div class="field">
            <div class="field-label">
                <label for="password">Contraseña actual</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="field-hint">
                        ¿Olvidaste tu contraseña?
                    </a>
                @endif
            </div>
            <div class="input-box">
                <input 
                    id="password" 
                    type="password" 
                    name="password" 
                    class="form-control @error('password') has-error @enderror" 
                    required 
                    autocomplete="current-password"
                    autofocus
                    placeholder="••••••••••••"
                >
                <button type="button" class="toggle-visibility" onclick="togglePass('password', this)" tabindex="-1" aria-label="Mostrar u ocultar contraseña">
                    <svg class="svg-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="svg-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>
            </div>
            @error('password')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn-action">
            <span>Confirmar y continuar</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
        </button>
    </form>
@endsection