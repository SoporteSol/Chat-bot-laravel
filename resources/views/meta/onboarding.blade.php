<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Conection</title>

    <!-- Tipografías e iconos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">

    <!-- Estilos del onboarding -->
    <link rel="stylesheet" href="{{ asset('css/meta-onboarding.css') }}?v={{ file_exists(public_path('css/meta-onboarding.css')) ? filemtime(public_path('css/meta-onboarding.css')) : time() }}">
</head>
<body>

    <div class="minimal-container">
        <!-- Icono WhatsApp -->
        <div class="minimal-icon">
            <i class="fab fa-whatsapp"></i>
        </div>

        @if($isExpired)
            <h1 class="minimal-title">Enlace no válido</h1>
            <p class="minimal-subtitle">
                Esta sesión de invitación ha expirado. Por favor solicita al administrador un nuevo enlace.
            </p>
        @elseif($isCompleted)
            <div class="minimal-icon" style="background: #ecfdf5; color: #10b981;">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="minimal-title">¡WhatsApp Conectado!</h1>
            <p class="minimal-subtitle">
                Tu número ha sido vinculado correctamente con la app. Ya puedes cerrar esta pestaña.
            </p>
        @else
            <h1 class="minimal-title">Conectar WhatsApp</h1>
            <p class="minimal-subtitle">
                Por favor inicia sesion con tu cuenta de Business
            </p>
            <!-- Botón de inicio de sesión -->
            <button type="button" id="btnIniciarMeta" class="btn-meta">
                <i class="fab fa-facebook-f"></i>
                <span>Continuar con Facebook</span>
            </button>

            <!-- Enlace alternativo si se bloquea la ventana emergente -->
            @php
                $fullScreenUrl = $metaConfigId 
                    ? "https://business.facebook.com/messaging/whatsapp/onboard/?app_id={$metaAppId}&config_id={$metaConfigId}"
                    : "https://www.facebook.com/v22.0/dialog/oauth?client_id={$metaAppId}&redirect_uri=" . urlencode(route('meta.onboarding.landing', ['token' => $token])) . "&scope=whatsapp_business_management,whatsapp_business_messaging,public_profile&response_type=code&auth_type=rerequest";
            @endphp
            <a href="{{ $fullScreenUrl }}" class="secondary-link">
                ¿No abre la ventana? Presina aqui para abrir en una nueva ventana
            </a>

            <!-- Estado de carga o mensaje de error -->
            <div id="statusAlert" class="status-alert {{ !empty($errorMessage) ? 'error' : '' }}" style="{{ !empty($errorMessage) ? 'display: block;' : '' }}">
                @if(!empty($errorMessage))
                    <i class="fas fa-exclamation-circle mr-1"></i> {{ $errorMessage }}
                @endif
            </div>
        @endif
    </div>

    <div class="minimal-footer">
        Conexión directa y certificada con WhatsApp Cloud API · Meta Platforms, Inc.
    </div>
    <div id="onboardingConfig"
         data-app-id="{{ $metaAppId }}"
         data-config-id="{{ $metaConfigId ?? '' }}"
         data-token="{{ $token }}"
         data-completar-url="{{ route('meta.onboarding.completar', ['token' => $token]) }}"
         style="display: none;"></div>

    <!-- SDK y script de onboarding -->
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/es_LA/sdk.js"></script>
    <script src="{{ asset('js/meta-onboarding.js') }}?v={{ file_exists(public_path('js/meta-onboarding.js')) ? filemtime(public_path('js/meta-onboarding.js')) : time() }}"></script>

</body>
</html>
