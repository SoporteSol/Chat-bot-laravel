@extends('layouts.app')

@section('title', 'Void')

@section('content')

    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert" style="border-radius: var(--radius-md);">
            <div class="font-weight-bold mb-1">
                <i class="fas fa-exclamation-circle mr-1"></i> Por favor revisa los siguientes campos:
            </div>
            <ul class="mb-0 pl-3 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Encabezado y acciones -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="m-0 font-weight-700" style="color: var(--text-primary); font-size: 1.1rem;">
                Instancias creadas     {{ $instancias->count() }} 
            </h5>
        </div>
        <div class="d-flex align-items-center gap-2">
            <form action="{{ route('instancias.demo') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn-saas btn-saas-secondary shadow-sm" title="Crear o activar instancia rápida con credenciales de prueba de Meta">
                    Test Meta
                </button>
            </form>

            <button type="button" class="btn-saas btn-saas-primary shadow-sm" data-toggle="modal" data-target="#modalCrearInstancia">
                <i class="fas fa-plus"></i> Nueva Instancia
            </button>
        </div>
    </div>

    @if($instancias->isEmpty())
        <div class="saas-card text-center py-5">
            <div class="saas-card-body py-5">
                <div class="company-avatar mx-auto mb-3" style="width: 64px; height: 64px; font-size: 1.6rem; border-radius: 16px;">
                    <i class="fas fa-store"></i>
                </div>
                <h4 class="font-weight-700 mb-2" style="color: var(--text-primary);">Aún no tienes instancias configuradas</h4>
                <p class="text-secondary mx-auto mb-4" style="max-width: 480px; font-size: 0.9rem;">
                    Conecta una empresa real o genera una instancia rápida con el número de prueba de Meta para comenzar a recibir y gestionar mensajes.
                </p>
                <div class="d-inline-flex gap-2">
                    <form action="{{ route('instancias.demo') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn-saas btn-saas-secondary shadow-sm">
                             Test Meta
                        </button>
                    </form>
                    <button type="button" class="btn-saas btn-saas-primary" data-toggle="modal" data-target="#modalCrearInstancia">
                        <i class="fas fa-plus"></i> Crear Primera Instancia
                    </button>
                </div>
            </div>
        </div>
    @else
        <div class="row">
            @foreach($instancias as $empresa)
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="company-card">
                        <div class="company-card-header">
                            <div class="d-flex align-items-center min-w-0" style="flex: 1;">
                                <div class="company-meta">
                                    <div class="company-name" title="{{ $empresa->nombre_empresa }}">
                                        {{ $empresa->nombre_empresa }}
                                    </div>
                                    <div class="company-phone">
                                        @if($empresa->telefono)
                                            <i class="fab fa-whatsapp text-success"></i> +{{ $empresa->telefono }}
                                        @else
                                            <span class="text-muted">Sin teléfono asignado</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="company-card-body">
                            <div class="company-info-row">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Phone ID:</span>
                                    <span class="font-mono text-truncate" style="max-width: 150px; font-weight: 600;">
                                        {{ $empresa->phone_number_id }}
                                    </span>
                                </div>
                            </div>
                            <!-- Acceso al panel de la instancia -->
                            <a href="{{ route('instancias.show', $empresa->id) }}" class="btn-saas btn-saas-primary btn-block text-center" style="width: 100%;">
                                <span>Abrir CRM & Suite Comercial</span>
                                <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>

                        <div class="company-card-footer">
                            <button type="button" class="btn-saas btn-saas-secondary btn-saas-sm" data-toggle="modal" data-target="#modalEditar{{ $empresa->id }}">
                                <i class="fas fa-sliders-h mr-1"></i> Parámetros
                            </button>

                            <form action="{{ route('instancias.destroy', $empresa->id) }}" method="POST" class="d-inline" data-empresa="{{ $empresa->nombre_empresa }}" onsubmit="return confirm('¿Seguro que deseas eliminar la empresa ' + this.dataset.empresa + '?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-saas btn-saas-secondary btn-saas-sm text-danger" title="Eliminar">
                                    <i class="fas fa-trash-alt mr-1"></i> Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modal para editar datos de la instancia -->
                <div class="modal fade" id="modalEditar{{ $empresa->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content saas-modal">
                            <div class="modal-header saas-modal-header">
                                <h5 class="modal-title font-weight-700 m-0">
                                   Configuracion
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form action="{{ route('instancias.update', $empresa->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="modal-body p-4">
                                    <div class="row">
                                        <div class="col-md-6 form-group">
                                            <label class="saas-label">Nombre de la Empresa</label>
                                            <input type="text" name="nombre_empresa" class="saas-input" value="{{ $empresa->nombre_empresa }}" required>
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label class="saas-label">Número de Teléfono (WhatsApp)</label>
                                            <input type="text" name="telefono" class="saas-input" value="{{ $empresa->telefono }}" placeholder="+502...">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 form-group">
                                            <label class="saas-label">Phone Number ID (Meta)</label>
                                            <input type="text" name="phone_number_id" class="saas-input font-mono" value="{{ $empresa->phone_number_id }}" required>
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label class="saas-label">WABA ID (Opcional)</label>
                                            <input type="text" name="waba_id" class="saas-input font-mono" value="{{ $empresa->waba_id }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer saas-modal-footer">
                                    <button type="button" class="btn-saas btn-saas-secondary" data-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn-saas btn-saas-primary">
                                        <i class="fas fa-save mr-1"></i> Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Modal para vincular nueva empresa con QR -->
    <div class="modal fade" id="modalCrearInstancia" tabindex="-1" role="dialog" aria-labelledby="modalCrearLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content saas-modal">
                <div class="modal-header saas-modal-header">
                    <div>
                        <h5 class="modal-title font-weight-700 m-0" id="modalCrearLabel">
                            <i class="fab fa-whatsapp text-success mr-2"></i> Coexistencia
                        </h5>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body p-4">
                    <!-- Código QR y enlace de invitación -->
                    <div id="boxOnboardingQR" class="qr-modal-container">
                        <h5 class="font-weight-800 mb-1" style="color: var(--text-primary); font-size: 1.25rem;">
                            Envia esto
                        </h5>

                        <!-- QR generado -->
                        <div class="qr-image-card">
                            <div id="qrLoadingSpinner" style="width: 210px; height: 210px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-circle-notch fa-spin text-primary" style="font-size: 2.2rem;"></i>
                            </div>
                            <img id="qrOnboardingImg" src="" alt="Código QR de Vinculación" style="display: none;" onload="document.getElementById('qrLoadingSpinner').style.display='none'; this.style.display='block';">
                        </div>

                        <!-- Estado del sondeo -->
                        <div>
                            <div id="qrStatusBadge" class="qr-tracker-badge is-waiting">
                                <span id="qrStatusText">Esperando vinculacion</span>
                            </div>
                        </div>

                        <!-- Enlace para compartir -->
                        <div class="onboarding-link-box" style="max-width: 520px; margin: 0 auto 16px;">
                            <input type="text" id="inputOnboardingUrl" class="onboarding-link-input" readonly value="Generando enlace...">
                            <button type="button" class="btn-saas btn-saas-secondary btn-saas-sm py-1 px-3" onclick="copiarEnlaceOnboarding()">
                                <i class="fas fa-copy mr-1"></i> <span id="btnCopiarText">Copiar</span>
                            </button>
                            <a id="btnEnviarWhatsappLink" href="#" target="_blank" class="btn-saas btn-saas-primary btn-saas-sm py-1 px-3 text-white text-decoration-none">
                                <i class="fab fa-whatsapp mr-1"></i> Enviar
                            </a>
                        </div>
                    </div>

                    <!-- Confirmación de vinculación exitosa -->
                    <div id="boxOnboardingSuccess" class="p-4 rounded-lg text-center" style="display: none; background-color: var(--brand-wa-subtle); border: 1px solid rgba(16, 185, 129, 0.3);">
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; background-color: var(--brand-wa); color: white; font-size: 1.75rem;">
                            <i class="fas fa-check"></i>
                        </div>
                        <h4 class="font-weight-800 text-success mb-2">¡Vinculacion exitosa!</h4>
                        <p class="text-secondary small mb-4">
                            <strong id="successNombreEmpresa" class="text-dark" style="font-size: 1rem;"></strong> ha otorgado todos los permisos requeridos. Los datos han sido registrados en tu sistema.
                        </p>
                        <a id="btnIrEntrenamiento" href="#" class="btn-saas btn-saas-primary font-weight-bold py-2 px-4" style="font-size: 1rem;">
                            <i class="fas fa-brain mr-2"></i> Pasar al Entrenamiento del Bot &rarr;
                        </a>
                    </div>
                </div>

                <div class="modal-footer saas-modal-footer">
                    <button type="button" class="btn-saas btn-saas-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Metadatos de configuración para onboarding -->
    <div id="meta-signup-config" 
         data-app-id="{{ $metaAppId }}" 
         data-config-id="{{ $metaConfigId ?? '' }}" 
         data-token-url="{{ route('meta.onboarding.token') }}"
         data-status-url="{{ url('/meta/onboarding-status') }}"
         style="display: none;"></div>
@stop

@section('js')
    <!-- SDK de Facebook -->
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/es_LA/sdk.js"></script>
    <script src="{{ asset('js/instancias.js') }}?v={{ filemtime(public_path('js/instancias.js')) }}"></script>
@stop
