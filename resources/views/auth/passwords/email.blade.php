@extends('layouts.auth')

@section('title', 'Recuperar contrasena')

@section('content')
    <h1 class="auth-title">Recuperar contrase&ntilde;a</h1>

    @if (session('status'))
        <div class="alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert-error">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <div class="field" style="margin-bottom: 20px;">
            <div class="field-label">
                <label for="email">Correo</label>
            </div>
            <div class="input-wrap">
                <input 
                    id="email" 
                    type="email" 
                    name="email" 
                    class="form-control @error('email') has-error @enderror" 
                    value="{{ old('email') }}" 
                    required 
                    autofocus 
                    placeholder="correo@ejemplo.com"
                >
            </div>
            @error('email')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn-submit">Enviar enlace</button>
    </form>
@endsection

@section('bottom')
    <p class="auth-bottom">
        <a href="{{ route('login') }}">Volver al inicio de sesi&oacute;n</a>
    </p>
@endsection