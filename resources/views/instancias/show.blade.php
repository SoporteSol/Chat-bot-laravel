@extends('layouts.app')

@section('title', $instancia->nombre_empresa)

@section('css')
    <link rel="stylesheet" href="{{ asset('css/floating-navbar.css') }}?v={{ file_exists(public_path('css/floating-navbar.css')) ? filemtime(public_path('css/floating-navbar.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/blueprint-builder.css') }}?v={{ file_exists(public_path('css/blueprint-builder.css')) ? filemtime(public_path('css/blueprint-builder.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/crm-suite.css') }}?v={{ file_exists(public_path('css/crm-suite.css')) ? filemtime(public_path('css/crm-suite.css')) : time() }}">
@stop

@section('content_header')
    <div class="crm-executive-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="crm-executive-title">
                    {{ $instancia->nombre_empresa }}
                </h1>
                @if($instancia->telefono)
                    <div class="text-muted small mt-1 font-mono">
                        <i class="fab fa-whatsapp text-success mr-1"></i>+{{ $instancia->telefono }}
                    </div>
                @endif
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                {{-- @if($isAdmin)
                    <button type="button" class="btn-executive btn-executive-glass" data-toggle="modal" data-target="#modalConfigTecnica">
                        <i class="fas fa-sliders-h mr-1 text-primary"></i> Ajustes del Negocio
                    </button>
                @endif --}}
                <button type="button" class="btn-executive btn-executive-primary" data-toggle="modal" data-target="#modalCrmNuevoLead">
                    <i class="fas fa-user-plus mr-1"></i> + Nuevo Lead
                </button>
            </div>
        </div>
    </div>
@stop

@section('content')
    <!-- BARRA FLOTANTE CENTRAL: OPCIONES Y HERRAMIENTAS DE LA VISTA ACTIVA -->
    <div class="floating-navbar-container">
        <div class="floating-dock-wrapper">
            <!-- Indicador sutil de la vista activa (Navegada desde el Sidebar Lateral) -->
            <div class="floating-view-indicator">
                <i id="currentViewIcon" class="view-indicator-icon fas fa-chart-pie text-primary"></i>
                <span id="currentViewTitle" class="view-indicator-label">Dashboard</span>
            </div>

            <div class="floating-dock-divider"></div>

            <!-- Opciones y Herramientas Contextuales de la Vista Activa -->
            <nav aria-label="Opciones de la vista activa" class="floating-tools-capsule" id="contextualFloatingNavbar">

                <!-- Opciones: Dashboard -->
                <div class="floating-context-group" data-view-tools="dashboard">
                    <button type="button" class="tool-button active" data-dash-view="all" data-action="Vista Completa" data-tooltip="Dashboard General">
                        <i class="fas fa-th-large"></i>
                        <span>Todo</span>
                    </button>
                    <button type="button" class="tool-button" data-dash-view="kpis" data-action="Métricas KPI" data-tooltip="Solo Tarjetas KPI">
                        <i class="fas fa-chart-bar"></i>
                        <span>KPIs</span>
                    </button>
                    <button type="button" class="tool-button" data-dash-view="funnel" data-action="Embudo Comercial" data-tooltip="Embudo de Conversión">
                        <i class="fas fa-filter"></i>
                        <span>Embudo</span>
                    </button>
                    <button type="button" class="tool-button" data-dash-view="activity" data-action="Conversaciones en Curso" data-tooltip="Triage de Chats Activos">
                        <i class="fas fa-comments"></i>
                        <span>En Curso</span>
                    </button>
                    <a href="{{ route('instancias.contactos.exportar', $instancia->id) }}" class="tool-button" data-action="Exportar CSV" data-tooltip="Exportar Contactos">
                        <i class="fas fa-file-csv"></i>
                        <span>CSV</span>
                    </a>
                </div>

                <!-- Opciones: Embudo (Pipeline) -->
                <div class="floating-context-group d-none" data-view-tools="pipeline">
                    <button type="button" class="tool-button active" data-pipeline-filter="all" data-action="Todos los Leads" data-tooltip="Todos los Leads">
                        <i class="fas fa-layer-group"></i>
                        <span>Todos</span>
                    </button>
                    <button type="button" class="tool-button" data-pipeline-filter="bot" data-action="Filtro: Solo Bot" data-tooltip="Atendidos por Bot">
                        <i class="fas fa-robot"></i>
                        <span>Solo Bot</span>
                    </button>
                    <button type="button" class="tool-button" data-pipeline-filter="human" data-action="Filtro: Asesor Humano" data-tooltip="Esperando Asesor">
                        <i class="fas fa-user-check"></i>
                        <span>Asesor</span>
                    </button>
                    <button type="button" class="tool-button" data-toggle="modal" data-target="#modalCrmEtapas" data-action="Configurar Etapas" data-tooltip="Editar Etapas del Embudo">
                        <i class="fas fa-sliders-h"></i>
                        <span>Etapas</span>
                    </button>   
                    <button type="button" class="tool-button" data-toggle="modal" data-target="#modalCrmAutomatizaciones" data-action="Automatizar Embudo" data-tooltip="Automatizaciones y Plantillas">
                        <i class="fas fa-bolt"></i>
                        <span>Automatizar</span>
                    </button>
                    {{-- <button type="button" class="tool-button-primary" data-toggle="modal" data-target="#modalCrmNuevoLead" data-action="Nuevo Lead" title="Añadir Nuevo Lead">
                        <i class="fas fa-plus"></i>
                        <span>Lead</span>
                    </button> --}}
                </div>

                <!-- Opciones: Chats -->
                <div class="floating-context-group d-none" data-view-tools="chats">
                    <button type="button" class="tool-button active" data-chat-filter="all" data-action="Todos los Chats" data-tooltip="Bandeja Completa">
                        <i class="fas fa-inbox"></i>
                        <span>Todos</span>
                    </button>
                    <button type="button" class="tool-button" data-chat-filter="sin_leer" data-action="Chats Sin Leer" data-tooltip="Mensajes No Leídos">
                        <i class="fas fa-envelope"></i>
                        <span>Sin Leer</span>
                    </button>
                    <button type="button" class="tool-button" data-chat-filter="asesor" data-action="Atención Asesor" data-tooltip="Chats con Asesor">
                        <i class="fas fa-user-check"></i>
                        <span>Asesor</span>
                    </button>
                    <button type="button" class="tool-button" data-chat-filter="cotizacion" data-action="En Cotización" data-tooltip="Prospectos Cotizando">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Cotización</span>
                    </button>
                    <button type="button" class="tool-button" data-chat-filter="tareas" data-action="Con Tareas" data-tooltip="Seguimientos Pendientes">
                        <i class="fas fa-clock"></i>
                        <span>Tareas</span>
                    </button>
                    <button type="button" class="tool-button" data-chat-filter="resuelta" data-action="Chats Resueltos" data-tooltip="Conversaciones Cerradas">
                        <i class="fas fa-check-circle"></i>
                        <span>Resueltas</span>
                    </button>
                    <button type="button" class="tool-button" data-chat-filter="bot" data-action="Atendidos por Bot" data-tooltip="IA Activa">
                        <i class="fas fa-robot"></i>
                        <span>Bot</span>
                    </button>
                </div>

                <!-- Opciones: Tareas -->
                <div class="floating-context-group d-none" data-view-tools="tareas">
                    <button type="button" class="tool-button active" data-task-view="pendientes" data-action="Tareas Pendientes" data-tooltip="Por Completar">
                        <i class="fas fa-hourglass-half"></i>
                        <span>Pendientes</span>
                    </button>
                    <button type="button" class="tool-button" data-task-view="completadas" data-action="Tareas Completadas" data-tooltip="Finalizadas">
                        <i class="fas fa-check-double"></i>
                        <span>Completadas</span>
                    </button>
                    <button type="button" class="tool-button" data-task-view="todas" data-action="Todas las Tareas" data-tooltip="Historial Total">
                        <i class="fas fa-list-ul"></i>
                        <span>Todas</span>
                    </button>
                    <button type="button" class="tool-button-primary" data-task-action="new" data-action="Nueva Tarea" title="Crear Nueva Tarea">
                        <i class="fas fa-plus"></i>
                        <span>Tarea</span>
                    </button>
                </div>

                <!-- Opciones: Listas y catálogo -->
                <div class="floating-context-group d-none" data-view-tools="listas">
                    <button type="button" class="tool-button active" data-list-view="contactos" data-action="Directorio de Contactos" data-tooltip="Contactos">
                        <i class="fas fa-users"></i>
                        <span>Contactos</span>
                    </button>
                    <button type="button" class="tool-button" data-list-view="catalogo" data-action="Catálogo de Productos" data-tooltip="Catálogo">
                        <i class="fas fa-box-open"></i>
                        <span>Catálogo</span>
                    </button>
                    <button type="button" class="tool-button" data-list-view="plantillas" data-action="Plantillas Rápidas" data-tooltip="Plantillas /">
                        <i class="fas fa-bolt"></i>
                        <span>Plantillas</span>
                    </button>
                    <button type="button" class="tool-button" data-list-view="difusion" data-action="Difusión Masiva" data-tooltip="Difusión">
                        <i class="fas fa-bullhorn"></i>
                        <span>Difusión</span>
                    </button>
                    <button type="button" class="tool-button" data-list-view="campos" data-action="Campos Personalizados" data-tooltip="Campos">
                        <i class="fas fa-sliders-h"></i>
                        <span>Campos</span>
                    </button>
                    <button type="button" class="tool-button" data-list-view="conocimiento" data-action="Base Conocimiento IA" data-tooltip="Base IA">
                        <i class="fas fa-brain"></i>
                        <span>Base IA</span>
                    </button>
                    <button type="button" class="tool-button" data-list-view="widget" data-action="Enlaces & Widget Web" data-tooltip="Widget Web">
                        <i class="fas fa-code"></i>
                        <span>Widget</span>
                    </button>
                    <a href="{{ route('instancias.contactos.exportar', $instancia->id) }}" class="tool-button" data-action="Exportar CSV" data-tooltip="Exportar CSV">
                        <i class="fas fa-file-csv"></i>
                        <span>CSV</span>
                    </a>
                </div>

                @if($isAdmin)
                    <!-- Opciones: Flujos bot -->
                    <div class="floating-context-group d-none" data-view-tools="nodos">
                        <button type="button" class="tool-button" data-node-tool="zoom-out" data-action="Alejar Lienzo" data-tooltip="Alejar (-)">
                            <i class="fas fa-search-minus"></i>
                            <span>Alejar</span>
                        </button>
                        <button type="button" class="tool-button" data-node-tool="zoom-in" data-action="Acercar Lienzo" data-tooltip="Acercar (+)">
                            <i class="fas fa-search-plus"></i>
                            <span>Acercar</span>
                        </button>
                        <button type="button" class="tool-button" data-node-tool="zoom-reset" data-action="Restablecer Vista" data-tooltip="Centrar Vista">
                            <i class="fas fa-compress-arrows-alt"></i>
                            <span>Centrar</span>
                        </button>
                        <button type="button" class="tool-button" data-node-tool="auto-layout" data-action="Organizando Nodos" data-tooltip="Auto-Organizar">
                            <i class="fas fa-magic"></i>
                            <span>Auto-Layout</span>
                        </button>
                        <button type="button" class="tool-button-primary" data-node-tool="add-node" data-action="Añadir Nodo" title="Añadir Nuevo Nodo">
                            <i class="fas fa-plus"></i>
                            <span>Nodo</span>
                        </button>
                    </div>

                    <!-- Opciones: Agente IA -->
                    <div class="floating-context-group d-none" data-view-tools="prompt">
                        <button type="button" class="tool-button active" data-prompt-tool="focus" data-action="Editor de Prompt IA" data-tooltip="Instrucciones IA">
                            <i class="fas fa-terminal"></i>
                            <span>Editor</span>
                        </button>
                        {{-- <button type="button" class="tool-button-primary" data-prompt-tool="save" data-action="Guardar Prompt IA" title="Guardar Entrenamiento IA">
                            <i class="fas fa-save"></i>
                            <span>Guardar</span>
                        </button> --}}
                    </div>
                @endif
            </nav>
        </div>
        <div id="floating-feedback" role="status" aria-live="polite" class="floating-feedback"></div>
    </div>

    <!-- =========================================================================
         1. PANEL: DASHBOARD & ANALÍTICA COMERCIAL
         ========================================================================= -->
    <div id="pane-dashboard" class="tab-pane-content">
        <!-- 4 Tarjetas Maestras de Rendimiento (Fila Ejecutiva 1x4) -->
        <div class="crm-dashboard-kpis-grid mb-4" id="dash-section-kpis">
            <!-- 1. Pipeline Activo -->
            <div class="dash-kpi-card">
                <div class="dash-kpi-header">
                    <span class="dash-kpi-label">Pipeline en Negociación</span>
                    <div class="dash-kpi-icon icon-indigo">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
                <div class="dash-kpi-val" id="dash-pipeline-value">$0</div>
                <div class="dash-kpi-sub justify-content-between">
                    <div>
                        <i class="fas fa-layer-group text-muted mr-1"></i>
                        <span><strong id="dash-total-leads" class="text-dark">0</strong> oportunidades</span>
                    </div>
                    <span class="badge badge-light border text-primary small px-2 py-0" style="font-size:0.7rem;">En Curso</span>
                </div>
            </div>

            <!-- 2. Ventas Ganadas -->
            <div class="dash-kpi-card">
                <div class="dash-kpi-header">
                    <span class="dash-kpi-label">Ventas Ganadas</span>
                    <div class="dash-kpi-icon icon-emerald">
                        <i class="fas fa-trophy"></i>
                    </div>
                </div>
                <div class="dash-kpi-val text-success" id="dash-won-value">$0</div>
                <div class="dash-kpi-sub justify-content-between">
                    <div>
                        <i class="fas fa-chart-line text-emerald mr-1"></i>
                        <span>Conversión: <strong id="dash-conversion-rate" class="text-dark">0%</strong></span>
                    </div>
                    <span class="badge badge-light border text-success small px-2 py-0" style="font-size:0.7rem;">Cerradas</span>
                </div>
            </div>

            <!-- 3. Mensajería WhatsApp -->
            <div class="dash-kpi-card">
                <div class="dash-kpi-header">
                    <span class="dash-kpi-label">Tráfico WhatsApp Cloud</span>
                    <div class="dash-kpi-icon icon-teal">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                </div>
                <div class="dash-kpi-val">{{ $totalInbound + $totalOutbound }}</div>
                <div class="dash-kpi-sub justify-content-between font-mono">
                    <div>
                        <i class="fas fa-arrow-down text-success mr-1"></i>{{ $totalInbound }} in
                        <span class="mx-1 text-muted">·</span>
                        <i class="fas fa-arrow-up text-primary mr-1"></i>{{ $totalOutbound }} out
                    </div>
                    <span class="badge badge-light border text-muted small px-2 py-0" style="font-size:0.7rem;">v22.0</span>
                </div>
            </div>

            <!-- 4. Coexistencia IA vs Humano -->
            <div class="dash-kpi-card">
                <div class="dash-kpi-header">
                    <span class="dash-kpi-label">Atención & Tareas</span>
                    <div class="dash-kpi-icon icon-purple">
                        <i class="fas fa-robot"></i>
                    </div>
                </div>
                <div class="dash-kpi-val" style="font-size: 1.55rem;" id="dash-bot-ratio">0 Bot · 0 Asesor</div>
                <div class="dash-kpi-sub justify-content-between">
                    <div>
                        <i class="fas fa-calendar-check text-warning mr-1"></i>
                        <span><strong id="dash-pending-tasks" class="text-dark">0</strong> tareas pendientes</span>
                    </div>
                    <span class="badge badge-light border text-purple small px-2 py-0" style="font-size:0.7rem;">Operativo</span>
                </div>
            </div>
        </div>

        <!-- Fila Principal: Embudo de Ventas (7 cols) + Triage de Actividad (5 cols) -->
        <div class="row">
            <!-- Columna Izquierda: Embudo de Conversión -->
            <div class="col-lg-7 mb-4" id="dash-section-funnel">
                <div class="dash-funnel-card shadow-sm border" style="border-radius: 16px; overflow: hidden; background: #ffffff;">
                    <div class="dash-card-header d-flex justify-content-between align-items-center py-3 px-4 bg-white border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-layer-group text-primary"></i>
                            <h3 class="dash-card-title mb-0" style="font-size: 1rem; font-weight: 700;">
                                Embudo de Conversión Comercial
                            </h3>
                            <span class="crm-stage-count-pill ml-2" id="dash-funnel-total-badge">$0 USD</span>
                        </div>
                        <a href="#pipeline" class="btn btn-xs btn-outline-primary font-weight-bold" data-nav="pipeline" style="font-size: 0.76rem; border-radius: 6px;">
                            <i class="fas fa-columns mr-1"></i> Tablero Kanban
                        </a>
                    </div>
                    <div class="dash-funnel-body p-3 px-4">
                        <!-- Barra segmentada de flujo del pipeline -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1 text-muted small" style="font-size: 0.75rem;">
                                <span>Distribución Visual de Oportunidades</span>
                                <span>Ticket promedio: <strong id="dash-avg-ticket" class="text-dark font-mono">$0</strong></span>
                            </div>
                            <div class="dash-segmented-flow-bar" id="dash-segmented-flow-bar" style="height: 8px; border-radius: 999px; overflow: hidden; display: flex; background: #e2e8f0;">
                                <!-- Llenado dinámico por JS -->
                            </div>
                        </div>

                        <!-- Grid de 2 columnas con tarjetas compactas de etapas -->
                        <div class="dash-funnel-grid" id="crm-dashboard-funnel">
                            <div class="text-center py-4 text-muted small">
                                <i class="fas fa-circle-notch fa-spin mr-2"></i> Cargando embudo comercial...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Triage de Conversaciones Activas -->
            <div class="col-lg-5 mb-4" id="dash-section-activity">
                <div class="dash-activity-card shadow-sm border" style="border-radius: 16px; overflow: hidden; background: #ffffff;">
                    <div class="dash-card-header d-flex justify-content-between align-items-center py-3 px-4 bg-white border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="status-pulse-dot mr-1"></span>
                            <h3 class="dash-card-title mb-0" style="font-size: 1rem; font-weight: 700;">
                                Conversaciones en Curso
                            </h3>
                            <span class="crm-stage-count-pill ml-1" id="dash-active-chats-badge">
                                {{ count($conversacionesActivas) }}
                            </span>
                        </div>
                        <a href="#chats" class="sidebar-tab-link text-muted small" data-nav="chats" style="font-weight: 600; text-decoration: none; font-size: 0.78rem;">
                            Ver bandeja <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <!-- Toolbar de triage rápido para filtrar la cola de conversaciones -->
                    <div class="dash-triage-toolbar py-2 px-3 border-bottom bg-light">
                        <button type="button" class="dash-triage-tab active" data-triage-filter="all">
                            Todas
                        </button>
                        <button type="button" class="dash-triage-tab" data-triage-filter="human">
                            Esperando Asesor
                        </button>
                        <button type="button" class="dash-triage-tab" data-triage-filter="unread">
                            Sin Leer
                        </button>
                    </div>

                    <div class="dash-conversations-queue" id="crm-dashboard-active-chats" style="max-height: 380px; overflow-y: auto;">
                        @forelse($conversacionesActivas as $conv)
                            @php
                                $cInitials = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $conv['nombre'] ?: 'WA'), 0, 2));
                                $stageKey = $conv['etapa'] ?? 'nuevo';
                                $stageMeta = $etapas[$stageKey] ?? ['label' => 'Nuevo', 'color' => '#3b82f6'];
                                $isHumanWait = !$conv['is_bot_active'];
                                $hasUnread = (int)($conv['mensajes_sin_leer'] ?? 0) > 0;
                            @endphp
                            <div class="crm-dash-chat-row" data-lead-id="{{ $conv['id'] }}" data-is-human="{{ $isHumanWait ? '1' : '0' }}" data-has-unread="{{ $hasUnread ? '1' : '0' }}">
                                <div class="dash-chat-avatar-wrapper">
                                    <div class="dash-chat-avatar">
                                        {{ $cInitials }}
                                    </div>
                                    <span class="dash-chat-status-dot {{ $isHumanWait ? 'dot-human' : 'dot-bot' }}" title="{{ $isHumanWait ? 'Atención Humana requerida' : 'Atendido por Bot' }}"></span>
                                </div>
                                <div class="dash-chat-body">
                                    <div class="dash-chat-header-row">
                                        <div class="d-flex align-items-center gap-1 text-truncate">
                                            <span class="dash-chat-name" title="{{ $conv['nombre'] }}">{{ $conv['nombre'] }}</span>
                                            <span class="dash-chat-phone">+{{ $conv['phone_number'] }}</span>
                                        </div>
                                        <span class="dash-chat-time">{{ $conv['ultimo_mensaje_human'] ?: $conv['updated_human'] }}</span>
                                    </div>
                                    <div class="dash-chat-preview-row">
                                        <div class="dash-chat-snippet text-truncate">
                                            @if($conv['ultimo_mensaje_direction'] === 'outbound')
                                                <i class="fas fa-reply text-muted mr-1" style="font-size:0.7rem;"></i>{{ $conv['ultimo_mensaje_body'] ?: 'Mensaje enviado' }}
                                            @else
                                                {{ $conv['ultimo_mensaje_body'] ?: 'Conversación abierta' }}
                                            @endif
                                        </div>
                                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                            @if($hasUnread)
                                                <span class="crm-unread-badge">{{ $conv['mensajes_sin_leer'] }}</span>
                                            @endif
                                            <span class="crm-badge" style="background: {{ $stageMeta['color'] ?? '#3b82f6' }}15; color: {{ $stageMeta['color'] ?? '#3b82f6' }}; font-size: 0.68rem; padding: 2px 6px;">
                                                {{ $stageMeta['label'] ?? $stageKey }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted small py-5">
                                <i class="fab fa-whatsapp fa-2x mb-2 text-muted" style="opacity: 0.35;"></i>
                                <div>Sin conversaciones activas en este momento.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Fila Inferior: Directorio Ejecutivo de Oportunidades y Leads Recientes -->
        <div class="row" id="dash-section-leads">
            <div class="col-12 mb-4">
                <div class="saas-card p-0 shadow-sm border" style="border-radius: 16px; overflow: hidden; background: #ffffff;">
                    <div class="p-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: #fafbfc;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-users-cog text-primary"></i>
                            <h4 class="font-weight-bold text-dark mb-0" style="font-size: 0.98rem;">Directorio de Oportunidades Recientes</h4>
                            <span class="badge badge-light border text-muted px-2 py-1 ml-2 small" id="dash-leads-table-count">0 contactos</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="#pipeline" data-nav="pipeline" class="btn btn-xs btn-outline-secondary font-weight-bold" style="border-radius:6px;">
                                <i class="fas fa-columns mr-1"></i> Ver Pipeline Kanban
                            </a>
                            <a href="#chats" data-nav="chats" class="btn btn-xs btn-primary font-weight-bold" style="border-radius:6px;">
                                <i class="fas fa-comments mr-1"></i> Abrir Bandeja de Chats
                            </a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                            <thead class="bg-light text-muted text-uppercase font-weight-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                                <tr>
                                    <th class="py-3 px-4">Contacto / Lead</th>
                                    <th class="py-3">Etapa en Embudo</th>
                                    <th class="py-3">Valor Estimado</th>
                                    <th class="py-3">Modo de Atención</th>
                                    <th class="py-3">Ventana 24h Meta</th>
                                    <th class="py-3">Último Mensaje</th>
                                    <th class="py-3 px-4 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="crm-dashboard-leads-table-body">
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted small">
                                        <i class="fas fa-circle-notch fa-spin mr-1"></i> Sincronizando directorio...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Embudo kanban -->
    <div id="pane-pipeline" class="tab-pane-content active">
        <div class="crm-pipeline-toolbar">
            <div class="crm-kpi-group">
                <div class="crm-kpi-pill">
                    <i class="fas fa-users text-primary"></i>
                    <span>Leads Totales:</span>
                    <strong id="kpi-total-leads">0</strong>
                </div>
                <div class="crm-kpi-pill">
                    <i class="fas fa-coins text-success"></i>
                    <span>Valor Pipeline:</span>
                    <strong id="kpi-total-value">$0</strong>
                </div>
                <div class="crm-kpi-pill">
                    <i class="fas fa-user-clock text-danger"></i>
                    <span>Atención Humana:</span>
                    <strong id="kpi-human-needed">0</strong>
                </div>
                <span id="pipelineFilteredCount" class="badge badge-light border text-muted px-2 py-1 small d-none" style="font-size: 0.75rem;"></span>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Ordenamiento inteligente -->
                <div class="d-flex align-items-center gap-1">
                    <label for="selectPipelineSort" class="text-muted small mb-0 mr-1" style="font-size: 0.76rem; white-space: nowrap;">
                        <i class="fas fa-sort-amount-down mr-1"></i>Orden:
                    </label>
                    <select id="selectPipelineSort" class="crm-topbar-select" style="width: auto; height: 34px; font-size: 0.8rem;" title="Ordenar leads en cada columna">
                        <option value="recent">Más recientes</option>
                        <option value="value_desc">Mayor valor ($$$)</option>
                        <option value="unread">Sin leer primero</option>
                        <option value="name_asc">Nombre (A - Z)</option>
                    </select>
                </div>

                <!-- Filtro Bot / Humano -->
                <select id="selectPipelineBotFilter" class="crm-topbar-select" style="width: auto; height: 34px; font-size: 0.8rem;" title="Filtrar por tipo de atención">
                    <option value="all">Todos los estados</option>
                    <option value="bot">Solo atendidos por Bot</option>
                    <option value="human">Solo intervención Humana</option>
                </select>

                <!-- Selector de Densidad: Compacta vs Detallada para 100+ leads -->
                <div class="crm-density-toggle" id="pipelineDensityToggle" title="Alternar densidad visual para gestionar decenas o cientos de leads">
                    <button type="button" class="crm-density-btn active" data-density="compact" title="Vista compacta: máxima visibilidad de hasta 15-20 leads por columna sin scroll excesivo">
                        <i class="fas fa-bars"></i>
                        <span>Compacta</span>
                    </button>
                    <button type="button" class="crm-density-btn" data-density="detailed" title="Vista expandida: muestra todas las etiquetas, tareas y detalles">
                        <i class="fas fa-th-large"></i>
                        <span>Expandida</span>
                    </button>
                </div>

                <!-- Buscador de leads -->
                <div class="position-relative">
                    <input 
                        type="text" 
                        id="inputPipelineSearch" 
                        class="saas-input py-1 px-3" 
                        style="width: 220px; height: 34px; font-size: 0.8rem;" 
                        placeholder="Buscar lead o #tag..."
                    >
                </div>
            </div>
        </div>

        <div class="crm-kanban-board" id="crm-kanban-board"></div>
    </div>

    <!-- Bandeja de chats -->
    <div id="pane-chats" class="tab-pane-content">
        <div class="crm-inbox-shell">
            <!-- Lista de conversaciones -->
            <div class="crm-inbox-sidebar">
                <div class="crm-inbox-search-header">
                    <input 
                        type="text" 
                        id="inputInboxSearch" 
                        class="saas-input py-1 px-3 w-100" 
                        style="height: 34px; font-size: 0.82rem;" 
                        placeholder="Buscar contacto, empresa o número..."
                    >
                    <div class="crm-filter-pills">
                        <button type="button" class="crm-filter-pill active" data-filter="all">Todos</button>
                        <button type="button" class="crm-filter-pill" data-filter="sin_leer">Sin Leer</button>
                        <button type="button" class="crm-filter-pill" data-filter="asesor">Humano</button>
                        <button type="button" class="crm-filter-pill" data-filter="cotizacion">Cotización</button>
                        <button type="button" class="crm-filter-pill" data-filter="tareas">Con Tareas</button>
                        <button type="button" class="crm-filter-pill" data-filter="resuelta">Resueltas</button>
                        <button type="button" class="crm-filter-pill" data-filter="bot">Bot</button>
                    </div>
                </div>

                <div class="crm-inbox-list" id="crm-inbox-list"></div>
            </div>

            <!-- Chat activo y respuestas rápidas -->
            <div class="crm-inbox-main">
                <div class="crm-chat-topbar">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <strong class="text-dark" id="inbox-active-name" style="font-size: 0.95rem;">Selecciona una conversación</strong>
                            {{-- <span id="inbox-active-node" class="node-slug-tag">#main</span>
                            <span id="inbox-active-window24h" class="badge badge-secondary d-none" style="font-size: 0.68rem;">Ventana 24h</span> --}}
                        </div>
                        <div class="font-mono text-success small font-weight-600" id="inbox-active-phone"></div>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <select id="inbox-select-estado-conv" class="crm-topbar-select" title="Estado de la conversación">
                            <option value="abierta">Abierta</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="resuelta">Resuelta</option>
                        </select>

                        <select id="inbox-select-asesor" class="crm-topbar-select" title="Asignar o transferir a un asesor">
                            <option value="">Sin asignar</option>
                            @foreach($usuariosEmpresa as $uEmp)
                                <option value="{{ $uEmp->id }}">{{ $uEmp->name }}</option>
                            @endforeach
                        </select>

                        <button type="button" class="btn-saas btn-saas-secondary" id="btnInboxIaResumen" title="Generar resumen ejecutivo de 3 puntos con IA">
                            <i class="fas fa-magic text-primary mr-1"></i> Resumen 
                        </button>
                        <button type="button" class="btn-saas btn-saas-secondary" id="btnInboxToggleBot" title="Alternar entre Bot automático e Intervención Humana">
                            <i class="fas fa-robot mr-1"></i> Bot Activo
                        </button>
                    </div>
                </div>

                <!-- Historial de mensajes -->
                <div class="crm-messages-viewport" id="crm-messages-viewport">
                    <div class="text-center text-muted small py-5">
                        Selecciona un contacto de la columna izquierda para abrir su conversación.
                    </div>
                </div>

                {{-- <!-- Copiloto y plantillas -->
                <div class="crm-copilot-bar">
                    <div id="crmTemplatesPopover" class="crm-templates-popover"></div>

                    <button type="button" class="crm-copilot-btn" id="btnToggleTemplatesPopover" title="Insertar Plantilla Rápida (/atajo)">
                        <i class="fas fa-bolt text-warning"></i> /Plantillas
                    </button>
                    <button type="button" class="crm-copilot-btn" id="btnCopilotSuggest" title="Redactar respuesta sugerida según el contexto">
                        <i class="fas fa-lightbulb text-primary"></i> Sugerir Respuesta
                    </button>
                    <button type="button" class="crm-copilot-btn btn-copilot-tone" data-tono="formal" title="Reescribir borrador en tono corporativo formal">
                        <i class="fas fa-user-tie"></i> Tono Ejecutivo
                    </button>
                    <button type="button" class="crm-copilot-btn btn-copilot-tone" data-tono="amigable" title="Reescribir borrador en tono cálido y amigable">
                        <i class="fas fa-smile"></i> Tono Amigable
                    </button>
                    <button type="button" class="crm-copilot-btn btn-copilot-tone" data-tono="ventas" title="Reescribir borrador enfocado en cierre comercial">
                        <i class="fas fa-trophy text-success"></i> Tono Cierre
                    </button>
                </div> --}}

                <!-- Campo de envío / nota interna / adjuntos -->
                <div class="crm-composer-bar" id="crmComposerContainer">
                    <!-- Vista previa de foto adjunta antes de enviar -->
                    <div id="crmComposerMediaPreview" class="d-none align-items-center gap-2 mb-2 p-2 bg-light rounded border">
                        <img id="crmComposerThumb" src="" alt="Vista previa" style="height: 48px; width: 48px; object-fit: cover; border-radius: 6px;">
                        <div class="flex-grow-1 overflow-hidden">
                            <div id="crmComposerFileName" class="small font-weight-bold text-truncate text-dark">foto.jpg</div>
                            <div id="crmComposerFileSize" class="small text-muted font-mono">0 KB</div>
                        </div>
                        <button type="button" id="btnRemoveComposerMedia" class="btn btn-xs btn-outline-danger py-1 px-2" title="Quitar imagen adjunta">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form id="formCrmInboxSend" class="d-flex gap-2 align-items-center m-0">
                        @csrf
                        <button type="button" id="btnToggleInternalNoteMode" class="btn-saas btn-saas-secondary px-2 py-2 flex-shrink-0" title="Alternar entre Mensaje de WhatsApp y Nota Interna del Equipo (solo visible para ti)">
                            <i class="fas fa-sticky-note text-warning"></i> <span class="d-none d-md-inline small">Nota Hilo</span>
                        </button>
                        <button type="button" id="btnInboxAttachMedia" class="btn-saas btn-saas-secondary px-2 py-2 flex-shrink-0" title="Adjuntar foto para enviar por WhatsApp">
                            <i class="fas fa-paperclip text-primary"></i>
                        </button>
                        <input type="file" id="inputInboxMediaFile" accept="image/jpeg,image/png,image/jpg,image/webp" class="d-none">
                        <input 
                            type="text" 
                            id="inputCrmInboxMessage" 
                            class="saas-input flex-grow-1" 
                            placeholder="Escribe un mensaje o adjunta una foto..."
                            autocomplete="off"
                        >
                        <button type="submit" id="btnCrmInboxSend" class="btn-saas btn-saas-primary px-3 py-2 flex-shrink-0">
                            <i class="fas fa-paper-plane mr-1"></i> Enviar
                        </button>
                    </form>
                </div>
            </div>

            <!-- Ficha del contacto -->
            <div class="crm-inspector">
                <div class="crm-inspector-tabs">
                    <button type="button" class="crm-inspector-tab active" data-inspector-tab="main">
                        <i class="fas fa-id-card mr-1"></i> Principal 360°
                    </button>
                    <button type="button" class="crm-inspector-tab" data-inspector-tab="products">
                        <i class="fas fa-shopping-bag mr-1"></i> Productos / Cotización
                    </button>
                    <button type="button" class="crm-inspector-tab" data-inspector-tab="timeline">
                        <i class="fas fa-history mr-1"></i> Auditoría / Timeline
                    </button>
                </div>

                <!-- Datos generales -->
                <div id="inspector-pane-main">
                    <div class="crm-section">
                        <div class="form-group mb-2">
                            <label class="saas-label small mb-1">Nombre del Contacto</label>
                            <input type="text" id="crm-input-nombre" class="saas-input py-1 px-2" style="height: 30px; font-size: 0.8rem;" placeholder="Nombre del cliente">
                        </div>

                        <div class="row no-gutters mb-2">
                            <div class="col-6 pr-1">
                                <label class="saas-label small mb-1">Empresa</label>
                                <input type="text" id="crm-input-empresa" class="saas-input py-1 px-2" style="height: 30px; font-size: 0.8rem;" placeholder="Empresa">
                            </div>
                            <div class="col-6 pl-1">
                                <label class="saas-label small mb-1">Email</label>
                                <input type="email" id="crm-input-email" class="saas-input py-1 px-2" style="height: 30px; font-size: 0.8rem;" placeholder="correo@cliente.com">
                            </div>
                        </div>

                        <div class="row no-gutters mb-2">
                            <div class="col-7 pr-1">
                                <label class="saas-label small mb-1">Etapa del Embudo</label>
                                <select id="crm-select-etapa" class="crm-topbar-select w-100">
                                    @foreach($etapas as $key => $meta)
                                        <option value="{{ $key }}">{{ $meta['label'] ?? $meta['nombre'] ?? $key }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-5 pl-1">
                                <label class="saas-label small mb-1">Presupuesto ($)</label>
                                <input type="number" step="0.01" min="0" id="crm-input-valor" class="saas-input font-mono py-1 px-2" style="height: 30px; font-size: 0.8rem;" placeholder="0.00">
                            </div>
                        </div>

                        <div id="crm-loss-reason-box" class="form-group mb-2 d-none">
                            <label class="saas-label small mb-1 text-danger"><i class="fas fa-times-circle mr-1"></i>Motivo de Pérdida *</label>
                            <input type="text" id="crm-input-motivo-perdida" class="saas-input py-1 px-2" style="height: 30px; font-size: 0.8rem; border-color: #fca5a5;" placeholder="Motivo por el que se perdió la oportunidad">
                        </div>

                        <div class="d-flex align-items-center justify-content-between pt-1">
                            <label class="small text-secondary mb-0 d-flex align-items-center gap-1" style="cursor: pointer;">
                                <input type="checkbox" id="crm-checkbox-opt-in" checked>
                                <span>Suscrito a Difusiones (Opt-in)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Resumen de la Conversacion con IA -->
                    <div class="crm-section" id="crm-section-ai-resumen">
                        <div class="crm-section-title d-flex justify-content-between align-items-center mb-2">
                            <span class="font-weight-bold" style="font-size: 0.82rem; color: #1e293b; text-transform: none; letter-spacing: normal;">
                                <i class="fas fa-magic text-primary mr-1"></i> Resumen de la Conversación
                            </span>
                            <button type="button" class="btn btn-xs btn-outline-primary px-2 py-0" id="btnSidebarGenerateSummary" title="Actualizar resumen de la conversación con IA">
                                <i class="fas fa-sync-alt mr-1"></i> Actualizar
                            </button>
                        </div>
                        <div id="crm-sidebar-summary-box" class="crm-sidebar-summary-box">
                            <span class="text-muted font-italic">Sin resumen generado aún. Haz clic en "Resumen" para sintetizar la conversación.</span>
                        </div>
                    </div>

                    <!-- Campos extra definidos para la empresa -->
                    <div class="crm-section" id="crm-custom-fields-section">
                        <div class="crm-section-title">
                            <span><i class="fas fa-sliders-h mr-1 text-indigo"></i> Campos Personalizados</span>
                        </div>
                        <div id="crm-custom-fields-container"></div>
                    </div>

                    <!-- Etiquetas -->
                    <div class="crm-section">
                        <div class="crm-section-title">
                            <span><i class="fas fa-tags mr-1 text-success"></i> Etiquetas</span>
                        </div>
                        <div id="crm-tags-container" class="crm-tag-list"></div>
                        <div class="d-flex gap-1">
                            <input type="text" id="crm-input-new-tag" class="saas-input py-1 px-2" style="height: 30px; font-size: 0.78rem;" placeholder="Nueva etiqueta...">
                            <button type="button" id="crm-btn-add-tag" class="btn-saas btn-saas-secondary btn-sm px-2 py-1 flex-shrink-0">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Tareas pendientes -->
                    <div class="crm-section">
                        <div class="crm-section-title">
                            <span><i class="fas fa-check-square mr-1 text-warning"></i> Tareas de Seguimiento</span>
                        </div>
                        <div id="crm-tasks-container" class="mb-2"></div>
                        <div class="d-flex flex-column gap-1 mb-1">
                            <div class="d-flex gap-1">
                                <select id="crm-select-task-type" class="crm-topbar-select flex-grow-1" style="min-width: 100px;">
                                    <option value="seguimiento">Seguimiento</option>
                                    <option value="llamada">Llamar</option>
                                    <option value="reunion">Reunión</option>
                                    <option value="cotizacion">Cotización</option>
                                </select>
                                <input type="date" id="crm-input-task-date" class="saas-input py-1 px-2" style="height: 28px; font-size: 0.78rem; width: 130px;">
                            </div>
                            <div class="d-flex gap-1">
                                <input type="text" id="crm-input-task-title" class="saas-input py-1 px-2 flex-grow-1" style="height: 28px; font-size: 0.78rem;" placeholder="Descripción de la tarea...">
                                <button type="button" id="crm-btn-add-task" class="btn-saas btn-saas-secondary btn-sm px-2 py-0 flex-shrink-0" title="Agregar Tarea">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Notas del equipo -->
                    <div class="crm-section" style="border-bottom: none;">
                        <div class="crm-section-title">
                            <span><i class="fas fa-sticky-note mr-1 text-secondary"></i> Notas Internas</span>
                        </div>
                        <div id="crm-notes-container" class="mb-2"></div>
                        <div class="d-flex flex-column gap-1">
                            <textarea id="crm-input-note-text" class="saas-input p-2" rows="2" style="font-size: 0.78rem;" placeholder="Añadir nota interna para el equipo..."></textarea>
                            <button type="button" id="crm-btn-add-note" class="btn-saas btn-saas-secondary btn-sm align-self-end py-1 px-2">
                                <i class="fas fa-plus mr-1"></i> Guardar Nota
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cotización y productos -->
                <div id="inspector-pane-products" class="d-none">
                    <div class="crm-section">
                        <div class="crm-section-title">
                            <span><i class="fas fa-boxes mr-1 text-primary"></i> Cotizador de Productos</span>
                        </div>
                        <p class="small text-muted mb-2">
                            Vincula productos del catálogo de la empresa a este Lead para calcular automáticamente el valor del negocio.
                        </p>

                        <div class="form-group mb-2">
                            <select id="crm-select-catalog-product" class="crm-topbar-select w-100"></select>
                        </div>
                        <div class="d-flex gap-2 mb-3">
                            <input type="number" id="crm-input-product-qty" class="saas-input py-1 px-2 font-mono" style="width: 80px; height: 32px; font-size: 0.8rem;" value="1" min="1">
                            <button type="button" id="crm-btn-add-product-to-lead" class="btn-saas btn-saas-primary btn-sm flex-grow-1">
                                <i class="fas fa-cart-plus mr-1"></i> Agregar al Lead
                            </button>
                        </div>

                        <div id="crm-lead-products-list" class="mb-3"></div>

                        <div class="d-flex justify-content-between align-items-center p-2" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                            <span class="small font-weight-bold text-secondary">Total Cotizado:</span>
                            <strong class="font-mono text-success" id="crm-lead-products-total" style="font-size: 1rem;">$0</strong>
                        </div>

                        <div class="mt-3 p-3" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:10px;">
                            <div class="small font-weight-bold text-dark mb-2"><i class="fas fa-file-invoice-dollar text-primary mr-1"></i> Cotización Oficial PDF</div>
                            <div class="row no-gutters mb-2">
                                <div class="col-7 pr-1">
                                    <label class="saas-label small mb-1">Folio Cotización</label>
                                    <input type="text" id="crm-quote-folio" class="saas-input py-1 px-2 font-mono" style="height: 28px; font-size: 0.75rem;" placeholder="COT-Auto">
                                </div>
                                <div class="col-5 pl-1">
                                    <label class="saas-label small mb-1">IVA %</label>
                                    <input type="number" id="crm-quote-tax" class="saas-input py-1 px-2 font-mono" style="height: 28px; font-size: 0.75rem;" value="{{ $datosFiscales['porcentaje_impuesto'] ?? 16 }}" min="0" max="100">
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" id="crm-btn-download-pdf-quote" class="btn-saas btn-saas-secondary btn-sm flex-fill" title="Descargar Cotización en PDF">
                                    <i class="fas fa-file-pdf text-danger mr-1"></i> PDF
                                </button>
                                <button type="button" id="crm-btn-send-whatsapp-quote" class="btn-saas btn-saas-primary btn-sm flex-fill" title="Enviar PDF oficial por WhatsApp">
                                    <i class="fab fa-whatsapp mr-1"></i> Enviar WhatsApp
                                </button>
                            </div>
                        </div>

                        <div class="mt-3" id="crm-lead-quotes-history-container">
                            <div class="crm-section-title">
                                <span><i class="fas fa-clock mr-1 text-secondary"></i> Cotizaciones Enviadas</span>
                            </div>
                            <div id="crm-lead-quotes-history-list" class="small text-muted"></div>
                        </div>
                    </div>
                </div>

                <!-- Timeline y cambios -->
                <div id="inspector-pane-timeline" class="d-none">
                    <div class="crm-section" style="border-bottom: none;">
                        <div class="crm-section-title d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-stream mr-1 text-primary"></i> Línea de Vida (Timeline)</span>
                            <span class="badge badge-light" id="crm-timeline-count">0 eventos</span>
                        </div>
                        <p class="small text-muted mb-2">
                            Historial cronológico inmutable de cambios de etapa, cotizaciones, notas y asignaciones.
                        </p>
                        <div id="crm-timeline-feed" class="crm-timeline-feed"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Centro de tareas y Calendario estilo Google -->
    <div id="pane-tareas" class="tab-pane-content">
        <div class="saas-card mb-3 crm-calendar-card">
            <!-- Barra superior estilo Google Calendar -->
            <div class="crm-calendar-topbar">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <button type="button" class="btn-gcal btn-gcal-today" id="btnCalendarToday">Hoy</button>
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn-gcal-icon" id="btnCalendarPrev" title="Anterior">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button type="button" class="btn-gcal-icon" id="btnCalendarNext" title="Siguiente">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <h3 class="crm-calendar-title mb-0" id="calendarCurrentMonthYear">Octubre de 2026</h3>
                </div>

                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Filtro por estado -->
                    <div class="crm-subnav mr-1">
                        <button type="button" class="crm-subnav-btn active" data-tasks-filter="pendientes">Pendientes</button>
                        <button type="button" class="crm-subnav-btn" data-tasks-filter="completadas">Completadas</button>
                        <button type="button" class="crm-subnav-btn" data-tasks-filter="todas">Todas</button>
                    </div>

                    <!-- Selector de Vista (Mes, Semana, Lista) -->
                    <div class="crm-subnav mr-2">
                        <button type="button" class="crm-subnav-btn active" data-calendar-view="month" title="Vista de mes">
                            <i class="fas fa-calendar-alt mr-1"></i> Mes
                        </button>
                        <button type="button" class="crm-subnav-btn" data-calendar-view="week" title="Vista de semana">
                            <i class="fas fa-calendar-week mr-1"></i> Semana
                        </button>
                        <button type="button" class="crm-subnav-btn" data-calendar-view="list" title="Vista de lista">
                            <i class="fas fa-list-ul mr-1"></i> Lista
                        </button>
                    </div>

                    <button type="button" class="btn-gcal-create" id="btnCalendarOpenCreate">
                        <i class="fas fa-plus mr-1"></i> Nueva Tarea
                    </button>
                </div>
            </div>

            <!-- Grilla de Calendario (Mes / Semana) -->
            <div id="calendar-grid-wrapper" class="crm-calendar-body">
                <div class="crm-calendar-weekdays" id="calendarWeekdaysHeader">
                    <div>LUN</div>
                    <div>MAR</div>
                    <div>MIÉ</div>
                    <div>JUE</div>
                    <div>VIE</div>
                    <div>SÁB</div>
                    <div>DOM</div>
                </div>
                <div class="crm-calendar-grid" id="calendarDaysGrid"></div>
            </div>

            <!-- Vista de Lista / Agenda -->
            <div id="calendar-list-wrapper" class="d-none p-3">
                <form id="formGlobalCreateTask" class="row no-gutters gap-2 align-items-center p-3 mb-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
                    <div class="col-md-3 pr-md-2 mb-2 mb-md-0">
                        <select id="global-task-lead-select" class="saas-input" required>
                            <option value="">-- Seleccionar Lead --</option>
                        </select>
                    </div>
                    <div class="col-md-2 pr-md-2 mb-2 mb-md-0">
                        <select id="global-task-type" class="saas-input">
                            <option value="seguimiento">Seguimiento</option>
                            <option value="llamada">Llamar</option>
                            <option value="reunion">Reunión</option>
                            <option value="cotizacion">Enviar Cotización</option>
                        </select>
                    </div>
                    <div class="col-md-3 pr-md-2 mb-2 mb-md-0">
                        <input type="text" id="global-task-title" class="saas-input" placeholder="Descripción de la tarea..." required>
                    </div>
                    <div class="col-md-2 pr-md-2 mb-2 mb-md-0">
                        <input type="date" id="global-task-due" class="saas-input">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn-saas btn-saas-primary w-100">
                            <i class="fas fa-plus"></i> Asignar
                        </button>
                    </div>
                </form>

                <div id="crm-global-tasks-list"></div>
            </div>
        </div>
    </div>

    <!-- Listas y recursos del CRM -->
    <div id="pane-listas" class="tab-pane-content">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="crm-subnav">
                <button type="button" class="crm-subnav-btn active" data-lists-subtab="contactos">
                    <i class="fas fa-users mr-1"></i> Contactos y Empresas
                </button>
                <button type="button" class="crm-subnav-btn" data-lists-subtab="catalogo">
                    <i class="fas fa-box-open mr-1"></i> Catálogo de Productos
                </button>
                <button type="button" class="crm-subnav-btn" data-lists-subtab="plantillas">
                    <i class="fas fa-bolt mr-1"></i> Plantillas Rápidas (/atajos)
                </button>
                <button type="button" class="crm-subnav-btn" data-lists-subtab="difusion">
                    <i class="fas fa-bullhorn mr-1"></i> Difusión Masiva (Broadcasts)
                </button>
                <button type="button" class="crm-subnav-btn" data-lists-subtab="campos">
                    <i class="fas fa-sliders-h mr-1"></i> Campos Personalizados
                </button>
                <button type="button" class="crm-subnav-btn" data-lists-subtab="importar">
                    <i class="fas fa-file-import mr-1"></i> Importar CSV
                </button>
                <button type="button" class="crm-subnav-btn" data-lists-subtab="conocimiento">
                    <i class="fas fa-brain mr-1"></i> Base IA (RAG)
                </button>
                <button type="button" class="crm-subnav-btn" data-lists-subtab="widget">
                    <i class="fas fa-code mr-1"></i> Enlaces & Widget
                </button>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('instancias.contactos.exportar', $instancia->id) }}" class="btn-saas btn-saas-secondary btn-sm">
                    <i class="fas fa-file-csv text-success mr-1"></i> Exportar Excel / CSV
                </a>
            </div>
        </div>

        <!-- Directorio de contactos -->
        <div id="subpane-contactos" class="saas-card">
            <div class="saas-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3 class="saas-card-title">Directorio Unificado de Contactos y Empresas</h3>
                <input type="text" id="inputDirectorySearch" class="saas-input py-1 px-3" style="width: 280px; height: 34px;" placeholder="Buscar por nombre, empresa, correo o teléfono...">
            </div>
            <div class="saas-card-body p-0 table-responsive">
                <table class="table table-hover mb-0 saas-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th>WhatsApp</th>
                            <th>Email</th>
                            <th>Etapa</th>
                            <th>Valor ($)</th>
                            <th>Etiquetas</th>
                            <th class="text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="crm-directory-tbody"></tbody>
                </table>
            </div>
        </div>

        <!-- Catálogo de productos -->
        <div id="subpane-catalogo" class="saas-card d-none">
            <div class="saas-card-header">
                <h3 class="saas-card-title">Catálogo de Productos y Servicios (Sincronizado con IA y Cotizador)</h3>
                <p class="text-muted small mb-0 mt-1">Todo producto registrado aquí puede añadirse a las cotizaciones de tus leads y es conocido automáticamente por tu Agente IA.</p>
            </div>
            <div class="saas-card-body">
                <form id="formCrmCatalog" class="row no-gutters gap-2 mb-3 p-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
                    <div class="col-md-2 pr-md-2 mb-2 mb-md-0">
                        <input type="text" name="sku" class="saas-input" placeholder="Código / SKU">
                    </div>
                    <div class="col-md-3 pr-md-2 mb-2 mb-md-0">
                        <input type="text" name="nombre" class="saas-input" placeholder="Nombre del Producto o Servicio" required>
                    </div>
                    <div class="col-md-2 pr-md-2 mb-2 mb-md-0">
                        <input type="text" name="categoria" class="saas-input" placeholder="Categoría">
                    </div>
                    <div class="col-md-2 pr-md-2 mb-2 mb-md-0">
                        <input type="number" step="0.01" min="0" name="precio" class="saas-input font-mono" placeholder="Precio ($)" required>
                    </div>
                    <div class="col-md-2 pr-md-2 mb-2 mb-md-0">
                        <input type="text" name="descripcion" class="saas-input" placeholder="Descripción">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn-saas btn-saas-primary w-100"><i class="fas fa-plus"></i></button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 saas-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Producto / Servicio</th>
                                <th>Categoría</th>
                                <th>Precio Unitario</th>
                                <th>Descripción</th>
                                <th class="text-right">Eliminar</th>
                            </tr>
                        </thead>
                        <tbody id="crm-catalog-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Plantillas rápidas -->
        <div id="subpane-plantillas" class="saas-card d-none">
            <div class="saas-card-header">
                <h3 class="saas-card-title">Plantillas de Respuesta Rápida de WhatsApp (Atajos `/`)</h3>
                <p class="text-muted small mb-0 mt-1">Escribe el comando (ej. <code>/saludo</code> o <code>/cuenta</code>) en el chat para autocompletar el mensaje. Soporta <code>@{{nombre}}</code> y <code>@{{telefono}}</code>.</p>
            </div>
            <div class="saas-card-body">
                <form id="formCrmTemplate" class="row no-gutters gap-2 mb-3 p-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
                    <div class="col-md-2 pr-md-2 mb-2 mb-md-0">
                        <input type="text" name="atajo" class="saas-input font-mono" placeholder="/atajo" required>
                    </div>
                    <div class="col-md-3 pr-md-2 mb-2 mb-md-0">
                        <input type="text" name="titulo" class="saas-input" placeholder="Título de la plantilla" required>
                    </div>
                    <div class="col-md-5 pr-md-2 mb-2 mb-md-0">
                        <input type="text" name="contenido" class="saas-input" placeholder="Contenido del mensaje..." required>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn-saas btn-saas-primary w-100"><i class="fas fa-save"></i></button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 saas-table">
                        <thead>
                            <tr>
                                <th>Atajo</th>
                                <th>Título</th>
                                <th>Contenido del Mensaje</th>
                                <th class="text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="crm-templates-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Difusión masiva -->
        <div id="subpane-difusion" class="saas-card d-none">
            <div class="saas-card-header">
                <h3 class="saas-card-title"><i class="fas fa-bullhorn text-primary mr-2"></i>Difusión Masiva por WhatsApp (Broadcasts)</h3>
                <p class="text-muted small mb-0 mt-1">Envía campañas o avisos personalizados a todos los leads de una etapa del embudo o etiqueta específica.</p>
            </div>
            <div class="saas-card-body">
                <form id="formCrmBroadcast" class="p-3 mb-4" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="saas-label">Nombre de la Campaña</label>
                            <input type="text" name="nombre_campana" class="saas-input" placeholder="Nombre de la campaña" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="saas-label">Filtrar por Etapa del Embudo</label>
                            <select name="filtro_etapa" class="saas-input">
                                <option value="all">Todas las etapas</option>
                                @foreach($etapas as $key => $meta)
                                    <option value="{{ $key }}">Solo etapa: {{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="saas-label">Filtrar por Etiqueta (Opcional)</label>
                            <input type="text" name="filtro_tag" class="saas-input" placeholder="Etiqueta a filtrar">
                        </div>
                    </div>
                    <div class="form-group mb-2">
                        <label class="saas-label">Mensaje de WhatsApp (puedes usar <code>@{{nombre}}</code> y <code>@{{empresa}}</code>)</label>
                        <textarea name="mensaje" class="saas-input" rows="3" placeholder="Escribe el mensaje de la difusión..." required></textarea>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn-saas btn-saas-primary">
                            <i class="fas fa-paper-plane mr-1"></i> Lanzar Difusión Masiva Ahora
                        </button>
                    </div>
                </form>

                <h6 class="font-weight-bold text-secondary text-uppercase small mb-2">Historial de Campañas Enviadas</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Campaña</th>
                                <th>Segmento</th>
                                <th>Mensaje</th>
                                <th>Métricas de Entrega / Opt-out</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody id="crm-broadcasts-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Campos personalizados -->
        <div id="subpane-campos" class="saas-card d-none">
            <div class="saas-card-header">
                <h3 class="saas-card-title"><i class="fas fa-sliders-h text-primary mr-2"></i>Campos Personalizados de la Ficha del Contacto</h3>
                <p class="text-muted small mb-0 mt-1">Define campos adicionales exclusivos para esta empresa (texto, número, fecha, lista desplegable o casilla de verificación).</p>
            </div>
            <div class="saas-card-body">
                <form id="formCrmCustomField" class="row no-gutters gap-2 mb-3 p-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
                    <div class="col-md-4 pr-md-2 mb-2 mb-md-0">
                        <input type="text" name="nombre" class="saas-input" placeholder="Nombre del campo" required>
                    </div>
                    <div class="col-md-3 pr-md-2 mb-2 mb-md-0">
                        <select name="tipo" id="selectCustomFieldType" class="saas-input">
                            <option value="texto">Texto libre</option>
                            <option value="numero">Número</option>
                            <option value="fecha">Fecha</option>
                            <option value="lista">Lista desplegable</option>
                            <option value="casilla">Casilla (Sí / No)</option>
                        </select>
                    </div>
                    <div class="col-md-3 pr-md-2 mb-2 mb-md-0">
                        <input type="text" name="opciones" id="inputCustomFieldOptions" class="saas-input" placeholder="Opciones separadas por coma (si es lista)">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn-saas btn-saas-primary w-100"><i class="fas fa-plus"></i></button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 saas-table">
                        <thead>
                            <tr>
                                <th>Clave</th>
                                <th>Nombre del Campo</th>
                                <th>Tipo de Dato</th>
                                <th>Opciones Configuradas</th>
                                <th class="text-right">Eliminar</th>
                            </tr>
                        </thead>
                        <tbody id="crm-custom-fields-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Importar desde CSV -->
        <div id="subpane-importar" class="saas-card d-none">
            <div class="saas-card-header">
                <h3 class="saas-card-title"><i class="fas fa-file-import text-primary mr-2"></i>Importación Masiva de Prospectos (CSV / Excel)</h3>
                <p class="text-muted small mb-0 mt-1">Carga bases de datos de prospectos desde archivos CSV con detección de delimitadores y mapeo dinámico de columnas.</p>
            </div>
            <div class="saas-card-body">
                <div class="p-4 mb-4 text-center" style="border: 2px dashed #cbd5e1; border-radius: 12px; background: #f8fafc;" id="csvDropZone">
                    <i class="fas fa-cloud-upload-alt text-primary mb-2" style="font-size: 2.5rem;"></i>
                    <h5 class="font-weight-bold text-dark mb-1">Selecciona o arrastra tu archivo CSV aquí</h5>
                    <p class="text-muted small mb-3">Soporta delimitadores estándar de comas o punto y coma (UTF-8 recomendado, máx. 10MB).</p>
                    <input type="file" id="csvFileInput" accept=".csv,.txt" class="d-none">
                    <button type="button" class="btn-saas btn-saas-primary btn-sm px-3" onclick="document.getElementById('csvFileInput').click();">
                        <i class="fas fa-folder-open mr-1"></i> Explorar Archivo
                    </button>
                    <div id="csvFileInfo" class="small text-muted mt-2 font-mono"></div>
                </div>

                <div id="csvMappingContainer" class="d-none">
                    <h5 class="font-weight-bold text-dark mb-2"><i class="fas fa-exchange-alt text-primary mr-1"></i> Mapeo de Columnas</h5>
                    <p class="small text-muted mb-3">Asocia las columnas detectadas en tu archivo con los campos del CRM:</p>

                    <div class="row mb-3" id="csvMappingFields">
                        <div class="col-md-3 form-group mb-2">
                            <label class="saas-label small text-danger">Teléfono / WhatsApp *</label>
                            <select id="mapColPhone" class="saas-input"></select>
                        </div>
                        <div class="col-md-3 form-group mb-2">
                            <label class="saas-label small">Nombre del Lead</label>
                            <select id="mapColName" class="saas-input"></select>
                        </div>
                        <div class="col-md-3 form-group mb-2">
                            <label class="saas-label small">Empresa</label>
                            <select id="mapColCompany" class="saas-input"></select>
                        </div>
                        <div class="col-md-3 form-group mb-2">
                            <label class="saas-label small">Correo Electrónico</label>
                            <select id="mapColEmail" class="saas-input"></select>
                        </div>
                        <div class="col-md-4 form-group mb-2">
                            <label class="saas-label small">Etiqueta Inicial</label>
                            <select id="mapColTag" class="saas-input"></select>
                        </div>
                        <div class="col-md-4 form-group mb-2">
                            <label class="saas-label small">Valor Estimado ($)</label>
                            <select id="mapColValue" class="saas-input"></select>
                        </div>
                        <div class="col-md-4 form-group mb-2">
                            <label class="saas-label small">Etapa del Embudo</label>
                            <select id="mapColStage" class="saas-input"></select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="saas-label small"><i class="fas fa-eye mr-1 text-secondary"></i> Vista Previa (Primeras 5 Filas)</label>
                        <div class="table-responsive" style="max-height: 200px; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <table class="table table-sm table-striped mb-0 saas-table" id="csvPreviewTable">
                                <thead class="thead-light"><tr id="csvPreviewHead"></tr></thead>
                                <tbody id="csvPreviewBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn-saas btn-saas-secondary btn-sm" id="btnCancelCsvImport">Cancelar</button>
                        <button type="button" class="btn-saas btn-saas-primary btn-sm px-4" id="btnRunCsvImport">
                            <i class="fas fa-play mr-1"></i> Iniciar Importación Masiva
                        </button>
                    </div>
                </div>

                <div id="csvResultCard" class="d-none mt-3 p-3" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;">
                    <h6 class="font-weight-bold text-success mb-2"><i class="fas fa-check-circle mr-1"></i> Importación Finalizada</h6>
                    <div class="row text-center font-mono" id="csvResultStats">
                        <div class="col-4">
                            <span class="d-block small text-muted">Creados</span>
                            <strong class="text-success h5" id="csvCountCreated">0</strong>
                        </div>
                        <div class="col-4">
                            <span class="d-block small text-muted">Actualizados</span>
                            <strong class="text-primary h5" id="csvCountUpdated">0</strong>
                        </div>
                        <div class="col-4">
                            <span class="d-block small text-muted">Omitidos / Inválidos</span>
                            <strong class="text-secondary h5" id="csvCountSkipped">0</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Base de conocimiento IA -->
        <div id="subpane-conocimiento" class="saas-card d-none">
            <div class="saas-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="saas-card-title"><i class="fas fa-brain text-primary mr-2"></i>Base de Conocimiento RAG para Inteligencia Artificial</h3>
                    <p class="text-muted small mb-0 mt-1">
                        Inyecta políticas comerciales, listas de preguntas frecuentes, términos y especificaciones que el Agente IA consultará al responder en tiempo real.
                    </p>
                </div>
                <button type="button" class="btn-saas btn-saas-primary btn-sm" id="btnOpenAddArticleModal">
                    <i class="fas fa-plus mr-1"></i> Añadir Artículo / FAQ
                </button>
            </div>
            <div class="saas-card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 saas-table">
                        <thead>
                            <tr>
                                <th>Título del Artículo / FAQ</th>
                                <th>Categoría</th>
                                <th>Palabras Clave</th>
                                <th>Resumen de Contenido</th>
                                <th class="text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="crm-knowledge-tbody"></tbody>
                    </table>
                </div>

                <!-- Formulario para nuevo artículo -->
                <div id="knowledgeArticleDrawer" class="d-none mt-4 p-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
                    <h5 class="font-weight-bold text-dark mb-2" id="knowledgeDrawerTitle"><i class="fas fa-edit text-primary mr-1"></i> Artículo de Conocimiento</h5>
                    <form id="formKnowledgeItem">
                        <input type="hidden" id="knowledgeItemId" value="">
                        <div class="row">
                            <div class="col-md-7 form-group mb-2">
                                <label class="saas-label small">Título o Pregunta Frecuente *</label>
                                <input type="text" id="knowledgeTitle" class="saas-input" placeholder="Ej: ¿Cuáles son las formas de pago aceptadas?" required>
                            </div>
                            <div class="col-md-5 form-group mb-2">
                                <label class="saas-label small">Categoría</label>
                                <input type="text" id="knowledgeCategory" class="saas-input" placeholder="Ej: Facturación, Envíos, Garantías">
                            </div>
                        </div>
                        <div class="form-group mb-2">
                            <label class="saas-label small">Palabras Clave (Separadas por comas)</label>
                            <input type="text" id="knowledgeKeywords" class="saas-input" placeholder="pago, transferencia, tarjeta, tarjeta de crédito, paypal">
                        </div>
                        <div class="form-group mb-3">
                            <label class="saas-label small">Contenido / Respuesta Completa para la IA *</label>
                            <textarea id="knowledgeContent" class="saas-input p-2" rows="4" placeholder="Escribe la explicación exacta que la IA debe tomar como fuente fidedigna..." required></textarea>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn-saas btn-saas-secondary btn-sm" id="btnCancelKnowledgeItem">Cancelar</button>
                            <button type="submit" class="btn-saas btn-saas-primary btn-sm px-3">
                                <i class="fas fa-save mr-1"></i> Guardar en Base de Conocimiento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Enlaces directos y widget web -->
        <div id="subpane-widget" class="saas-card d-none">
            <div class="saas-card-header">
                <h3 class="saas-card-title"><i class="fas fa-code text-primary mr-2"></i>Generador de Enlaces Directos & Widget Web Embebible</h3>
                <p class="text-muted small mb-0 mt-1">
                    Capta prospectos desde cualquier sitio web, anuncio publicitario o red social directamente hacia tu embudo.
                </p>
            </div>
            <div class="saas-card-body">
                <div class="row">
                    <!-- Generador wa.me -->
                    <div class="col-md-6 mb-4">
                        <div class="p-3 h-100" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                            <h5 class="font-weight-bold text-dark mb-2"><i class="fab fa-whatsapp text-success mr-1"></i> Enlace Directo wa.me</h5>
                            <p class="small text-muted mb-3">Genera links personalizados con mensaje inicial y parámetros UTM para medir campañas de marketing.</p>
                            
                            <div class="form-group mb-2">
                                <label class="saas-label small">Número de WhatsApp</label>
                                <input type="text" id="linkPhone" class="saas-input font-mono" value="{{ $instancia->telefono }}" placeholder="5215500000000">
                            </div>
                            <div class="form-group mb-2">
                                <label class="saas-label small">Mensaje Prellenado</label>
                                <input type="text" id="linkMessage" class="saas-input" value="Hola, deseo información sobre sus productos." placeholder="Mensaje inicial del prospecto">
                            </div>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <label class="saas-label small">UTM Source</label>
                                    <input type="text" id="linkUtmSource" class="saas-input font-mono py-1" value="web" placeholder="facebook, google, web">
                                </div>
                                <div class="col-6">
                                    <label class="saas-label small">UTM Campaign</label>
                                    <input type="text" id="linkUtmCampaign" class="saas-input font-mono py-1" value="direct" placeholder="promo_verano">
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <label class="saas-label small">URL Generada</label>
                                <div class="input-group">
                                    <input type="text" id="linkResultUrl" class="saas-input font-mono small py-1" readonly>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnCopyLink"><i class="fas fa-copy"></i> Copiar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón flotante para sitios web -->
                    <div class="col-md-6 mb-4">
                        <div class="p-3 h-100" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                            <h5 class="font-weight-bold text-dark mb-2"><i class="fas fa-comment-dots text-primary mr-1"></i> Widget de Chat para Sitio Web</h5>
                            <p class="small text-muted mb-3">Inserta este botón flotante en cualquier sitio web (HTML, WordPress, Shopify, Webflow) con solo pegar una línea de código antes de &lt;/body&gt;:</p>
                            
                            <div class="form-group mb-3">
                                <label class="saas-label small">Código Embebible (HTML / JS)</label>
                                <textarea id="widgetSnippetCode" class="saas-input font-mono small p-2" rows="6" readonly style="background:#1e293b; color:#e2e8f0; font-size:0.75rem;"></textarea>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-muted"><i class="fas fa-bolt text-warning mr-1"></i> Cero dependencias externas</span>
                                <button type="button" class="btn-saas btn-saas-primary btn-sm" id="btnCopyWidgetCode">
                                    <i class="fas fa-copy mr-1"></i> Copiar Código del Widget
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($isAdmin)
        <!-- Constructor de flujos -->
        <div id="pane-nodos" class="tab-pane-content">
            <div class="n8n-wrapper">
                <div class="n8n-toolbar">
                    <div class="d-flex align-items-center gap-2">
                        <span class="n8n-flow-title">Constructor de Flujos</span>
                        <span class="text-muted small ml-2 font-mono" id="n8n-count-display">{{ $nodes->count() }} nodos</span>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="n8n-zoom-controls">
                            <button type="button" class="n8n-zoom-btn" id="btn-zoom-out" title="Alejar (-)"><i class="fas fa-minus"></i></button>
                            <span class="n8n-zoom-level" id="n8n-zoom-text">100%</span>
                            <button type="button" class="n8n-zoom-btn" id="btn-zoom-in" title="Acercar (+)"><i class="fas fa-plus"></i></button>
                            <button type="button" class="n8n-zoom-btn" id="btn-zoom-reset" title="Restablecer vista"><i class="fas fa-compress-arrows-alt"></i></button>
                            <button type="button" class="n8n-zoom-btn" id="btn-auto-layout" title="Organizar nodos en columnas limpias"><i class="fas fa-magic"></i> Organizar</button>
                        </div>

                        <button type="button" class="btn-n8n-add" id="btn-add-node-canvas">
                            <i class="fas fa-plus"></i> Añadir Nodo
                        </button>
                    </div>
                </div>

                <div class="n8n-viewport" id="n8n-viewport">
                    <div id="n8n-empty-watermark" class="n8n-empty-blueprint">
                        <div class="n8n-empty-blueprint-icon"><i class="fas fa-project-diagram"></i></div>
                        <div class="n8n-empty-blueprint-title">Lienzo de Flujos Vacío</div>
                        <div class="n8n-empty-blueprint-desc">Haz clic en <strong>+ Añadir Nodo</strong> para crear el primer bloque conversacional de esta instancia.</div>
                    </div>

                    <div class="n8n-world" id="n8n-world">
                        <svg class="n8n-connections-svg" id="n8n-connections-svg"></svg>
                        <div id="n8n-nodes-layer"></div>
                    </div>

                    <div id="ue5-canvas-menu" class="ue5-context-menu">
                        <div class="ue5-context-menu-item" id="menu-item-auto-layout"><i class="fas fa-magic text-muted"></i> Organizar Nodos</div>
                        <div class="ue5-context-menu-item" id="menu-item-reset-view"><i class="fas fa-compress-arrows-alt text-muted"></i> Restablecer Vista</div>
                    </div>

                    <div class="n8n-drawer" id="n8n-drawer">
                        <div class="n8n-drawer-header">
                            <div class="d-flex align-items-center gap-2">
                                <div class="n8n-badge-icon badge-regular" id="drawer-node-badge"></div>
                                <div>
                                    <h5 class="n8n-drawer-title" id="drawer-title"></h5>
                                    <span class="font-mono text-muted small" id="drawer-subtitle">#main</span>
                                </div>
                            </div>
                            <button type="button" class="close text-light" id="btn-close-drawer" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                        </div>

                        <div class="n8n-drawer-body">
                            <div class="form-group mb-3">
                                <label class="saas-label font-weight-600 mb-1">Identificador del Nodo</label>
                                <input type="text" id="drawer_input_id" class="saas-input font-mono" placeholder="main">
                            </div>

                            <div class="form-group mb-3">
                                <label class="saas-label font-weight-600 mb-1"><i class="fab fa-whatsapp text-success mr-1"></i> Mensaje enviado por WhatsApp:</label>
                                <textarea id="drawer_input_message" class="saas-input font-mono" rows="4" placeholder="Mensaje que recibe el cliente"></textarea>
                            </div>

                            <div class="form-group mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="saas-label font-weight-600 mb-0"><i class="fas fa-image text-primary mr-1"></i> Foto / Imagen del Nodo (Opcional):</label>
                                    <button type="button" class="btn btn-xs btn-outline-primary" id="btnUploadNodeImage" title="Subir imagen desde tu computadora">
                                        <i class="fas fa-upload mr-1"></i> Subir Foto
                                    </button>
                                </div>
                                <input type="file" id="drawer_node_file_input" accept="image/jpeg,image/png,image/jpg,image/webp" class="d-none">
                                <input type="url" id="drawer_input_media_url" class="saas-input font-mono" style="font-size: 0.8rem;" placeholder="https://ejemplo.com/catalogo.jpg o sube un archivo">
                                
                                <div id="drawerNodeMediaPreview" class="d-none mt-2 position-relative text-center p-2 border rounded bg-light">
                                    <img id="drawerNodeMediaThumb" src="" alt="Vista previa del nodo" style="max-height: 120px; max-width: 100%; border-radius: 6px; object-fit: contain;">
                                    <button type="button" id="btnRemoveDrawerMedia" class="btn btn-xs btn-danger position-absolute" style="top: 5px; right: 5px;" title="Quitar imagen">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="p-2 mb-3" style="background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 10px;">
                                <div class="small font-weight-bold text-info mb-2"><i class="fas fa-bolt mr-1"></i> Acción Automática en Embudo CRM</div>
                                <div class="form-group mb-2">
                                    <label class="saas-label small mb-1">Mover Lead a Etapa (Opcional)</label>
                                    <select id="drawer_input_target_etapa" class="saas-input">
                                        <option value="">-- No cambiar etapa --</option>
                                        @foreach($etapas as $key => $meta)
                                            <option value="{{ $key }}">Mover a: {{ $meta['label'] ?? $meta['nombre'] ?? $key }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="saas-label small mb-1">Asignar Etiquetas Automáticas (separadas por coma)</label>
                                    <input type="text" id="drawer_input_auto_tags" class="saas-input" placeholder="ej: Interesado, Cotización, VIP">
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="saas-label n8n-drawer-label-uppercase">Ramas / Opciones del Cliente</label>
                                    <button type="button" class="btn btn-xs btn-outline-primary" id="btn-drawer-add-opt"><i class="fas fa-plus mr-1"></i> Añadir Rama</button>
                                </div>

                                <div id="drawer-notice" class="drawer-notice"></div>

                                <div id="new-branch-panel" class="new-branch-panel">
                                    <div class="new-branch-panel-header">
                                        <span class="new-branch-panel-title"><i class="fas fa-code-branch text-primary"></i> Nueva Rama</span>
                                        <button type="button" class="new-branch-panel-close" id="btn-cancel-new-branch" aria-label="Cerrar">&times;</button>
                                    </div>
                                    <div class="row no-gutters mb-2">
                                        <div class="col-4 pr-1">
                                            <label class="new-branch-label">Tecla</label>
                                            <input type="text" id="new-branch-key" class="saas-input new-branch-input-key" placeholder="1">
                                        </div>
                                        <div class="col-8">
                                            <label class="new-branch-label">Texto de la opción</label>
                                            <input type="text" id="new-branch-text" class="saas-input new-branch-input-text" placeholder="ej: Ver Catálogo">
                                        </div>
                                    </div>
                                    <div class="new-branch-field-group">
                                        <label class="new-branch-label">Saltar al nodo (Opcional)</label>
                                        <select id="new-branch-next" class="saas-input new-branch-select">
                                            <option value="">-- Sin salto (Respuesta final) --</option>
                                        </select>
                                    </div>
                                    <div class="new-branch-actions">
                                        <button type="button" class="btn-saas btn-saas-secondary new-branch-btn" id="btn-cancel-new-branch-2">Cancelar</button>
                                        <button type="button" class="btn-saas btn-saas-primary new-branch-btn" id="btn-submit-new-branch"><i class="fas fa-check mr-1"></i> Guardar Rama</button>
                                    </div>
                                </div>

                                <div id="drawer-options-list" class="d-flex flex-column gap-2"></div>
                            </div>
                        </div>

                        <div class="n8n-drawer-footer">
                            <button type="button" class="btn-saas btn-saas-danger btn-sm" id="btn-drawer-delete-node"><i class="fas fa-trash-alt mr-1"></i> Eliminar Nodo</button>
                            <button type="button" class="btn-saas btn-saas-primary btn-sm" id="btn-drawer-save-node"><i class="fas fa-save mr-1"></i> Guardar Cambios</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Prompt del agente IA -->
        <div id="pane-prompt" class="tab-pane-content">
            <div class="row">
                <div class="col-lg-12 mb-4 mb-lg-0">
                    <div class="saas-card h-100 d-flex flex-column">
                        <div class="saas-card-header">
                            <h3 class="saas-card-title">Entrenamiento e Instrucciones del Agente IA</h3>
                        </div>

                        <form action="{{ route('instancias.update', $instancia->id) }}" method="POST" class="saas-card-body d-flex flex-column flex-grow-1">
                            @csrf
                            @method('PUT')

                            <input type="hidden" name="nombre_empresa" value="{{ $instancia->nombre_empresa }}">
                            <input type="hidden" name="phone_number_id" value="{{ $instancia->phone_number_id }}">
                            <input type="hidden" name="waba_id" value="{{ $instancia->waba_id }}">
                            <input type="hidden" name="telefono" value="{{ $instancia->telefono }}">

                            <div class="form-group flex-grow-1 d-flex flex-column mb-3">
                                <textarea 
                                    id="live_system_prompt" 
                                    name="system_prompt" 
                                    class="prompt-editor-box flex-grow-1 font-mono" 
                                    placeholder="Escribe el prompt del agente de IA"
                                >{{ $instancia->system_prompt }}</textarea>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-2">
                                <button type="submit" id="btnGuardarPrompt" class="btn-saas btn-saas-primary font-weight-bold" disabled>
                                    <i class="fas fa-save mr-1"></i> Guardar Entrenamiento IA
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: nuevo contacto -->
    <div class="modal fade" id="modalCrmNuevoLead" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content saas-modal">
                <div class="modal-header saas-modal-header">
                    <h5 class="modal-title font-weight-bold text-dark"><i class="fas fa-user-plus text-primary mr-2"></i>Añadir Nuevo Lead al Embudo</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <form id="formCrmCreateLead">
                    <div class="modal-body p-4">
                        <div class="form-group">
                            <label class="saas-label">Nombre Completo *</label>
                            <input type="text" name="nombre" class="saas-input" placeholder="Nombre del contacto" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="saas-label">Teléfono WhatsApp *</label>
                                <input type="text" name="phone_number" class="saas-input font-mono" placeholder="Número de WhatsApp" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="saas-label">Empresa / Negocio</label>
                                <input type="text" name="empresa_cliente" class="saas-input" placeholder="Empresa o negocio">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="saas-label">Correo Electrónico</label>
                                <input type="email" name="email" class="saas-input" placeholder="Correo electrónico">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="saas-label">Valor Estimado ($)</label>
                                <input type="number" step="0.01" min="0" name="valor_estimado" class="saas-input font-mono" value="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-0">
                                <label class="saas-label">Etapa Inicial</label>
                                <select name="etapa" class="saas-input">
                                    @foreach($etapas as $key => $meta)
                                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label class="saas-label">Etiquetas (separadas por coma)</label>
                                <input type="text" name="etiquetas" class="saas-input" placeholder="Etiquetas separadas por coma">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer saas-modal-footer">
                        <button type="button" class="btn-saas btn-saas-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-saas btn-saas-primary"><i class="fas fa-check mr-1"></i> Crear Lead</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: automatizaciones por etapa -->
    <div class="modal fade" id="modalCrmAutomatizaciones" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content saas-modal">
                <div class="modal-header saas-modal-header">
                    <h5 class="modal-title font-weight-bold text-dark">Automatizaciones del Embudo Digital</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <form id="formCrmStageAutomations">
                    <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                        <p class="small text-muted mb-3">
                            Configura qué acciones automáticas debe ejecutar el sistema cuando un Lead entra a cada etapa del embudo (mensaje automático de WhatsApp, etiqueta automática, creación de tarea o pausa/activación del bot).
                        </p>
                        @foreach($etapas as $key => $meta)
                            @php
                                $regla = $pipelineAutomations[$key] ?? [];
                            @endphp
                            <div class="p-3 mb-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid {{ $meta['color'] }}; border-radius:10px;">
                                <div class="font-weight-bold text-dark mb-2">
                                    <i class="{{ $meta['icon'] }} mr-1" style="color:{{ $meta['color'] }}"></i> Cuando un Lead entra a: {{ $meta['label'] }}
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group mb-2">
                                        <label class="saas-label small mb-1">Enviar WhatsApp automático (Opcional)</label>
                                        <input type="text" id="auto_msg_{{ $key }}" class="saas-input py-1 px-2" value="{{ $regla['mensaje_whatsapp'] ?? '' }}" placeholder="Ej: Hola @{{nombre}}, estamos preparando tu propuesta...">
                                    </div>
                                    <div class="col-md-6 form-group mb-2">
                                        <label class="saas-label small mb-1">Crear Tarea Automática (Opcional)</label>
                                        <input type="text" id="auto_task_{{ $key }}" class="saas-input py-1 px-2" value="{{ $regla['crear_tarea'] ?? '' }}" placeholder="Ej: Llamar para confirmar cierre">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group mb-0">
                                        <label class="saas-label small mb-1">Agregar Etiqueta Automática</label>
                                        <input type="text" id="auto_tag_{{ $key }}" class="saas-input py-1 px-2" value="{{ $regla['auto_tag'] ?? '' }}" placeholder="Ej: En Seguimiento">
                                    </div>
                                    <div class="col-md-6 form-group mb-0">
                                        <label class="saas-label small mb-1">Acción sobre el Bot IA</label>
                                        <select id="auto_bot_{{ $key }}" class="saas-input py-1 px-2">
                                            <option value="ninguna" {{ ($regla['accion_bot'] ?? '') === 'ninguna' ? 'selected' : '' }}>Sin cambio</option>
                                            <option value="pausar" {{ ($regla['accion_bot'] ?? '') === 'pausar' ? 'selected' : '' }}>Pausar Bot (Pasar a Humano)</option>
                                            <option value="activar" {{ ($regla['accion_bot'] ?? '') === 'activar' ? 'selected' : '' }}>Activar Bot</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="modal-footer saas-modal-footer">
                        <button type="button" class="btn-saas btn-saas-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-saas btn-saas-primary"><i class="fas fa-save mr-1"></i> Guardar Disparadores del Embudo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: personalizar etapas -->
    <div class="modal fade" id="modalCrmEtapas" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content saas-modal">
                <div class="modal-header saas-modal-header">
                    <h5 class="modal-title font-weight-bold text-dark"><i class="fas fa-columns text-primary mr-2"></i>Configurar Etapas del Embudo de Ventas</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <form id="formCrmStagesConfig">
                    <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                        <p class="small text-muted mb-3">
                            Personaliza las columnas del embudo para esta empresa (nombre, color e icono). Las etapas finales <strong>Ganado</strong> y <strong>Perdido</strong> se mantienen siempre activas para las métricas de cierre.
                        </p>
                        <div id="crm-stages-editor-list" class="d-flex flex-column gap-2 mb-3"></div>
                        <div class="d-flex justify-content-between align-items-center">
                            <button type="button" id="btnAddStageRow" class="btn-saas btn-saas-secondary btn-sm">
                                <i class="fas fa-plus mr-1"></i> Añadir Nueva Etapa
                            </button>
                            <button type="button" id="btnResetDefaultStages" class="btn btn-xs btn-outline-secondary">
                                <i class="fas fa-undo mr-1"></i> Restaurar Etapas Predeterminadas
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer saas-modal-footer">
                        <button type="button" class="btn-saas btn-saas-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-saas btn-saas-primary"><i class="fas fa-save mr-1"></i> Guardar Etapas del Embudo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($isAdmin)
        <!-- Modal: Ajustes y Configuración del Negocio -->
        <div class="modal fade" id="modalConfigTecnica" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
                <div class="modal-content saas-modal">
                    <div class="modal-header saas-modal-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <div class="crm-avatar" style="width: 34px; height: 34px; background: #e0f2fe; color: #0284c7; font-size: 0.95rem;">
                                {{-- <i class="fas fa-sliders-h"></i> --}}
                            </div>
                            <div>
                                {{-- <h5 class="modal-title font-weight-bold text-dark mb-0">
                                    Ajustes y Parámetros del Negocio
                                </h5> --}}
                                <div class="small text-muted" style="font-size: 0.76rem;">Configuracion general</div>
                            </div>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    </div>

                    <!-- 4 Pestañas Principales y Claras -->
                    <div class="px-4 py-3 bg-light border-bottom">
                        <div class="crm-subnav" id="settingsModalNav">
                            <button type="button" class="crm-subnav-btn active" data-cfg-tab="general">
                                <i class="fab fa-whatsapp mr-1 text-success"></i> Mi Negocio & WhatsApp
                            </button>
                            <button type="button" class="crm-subnav-btn" data-cfg-tab="horarios">
                                <i class="fas fa-clock mr-1 text-warning"></i> Horarios de Atención
                            </button>
                            <button type="button" class="crm-subnav-btn" data-cfg-tab="usuarios">
                                <i class="fas fa-users mr-1 text-primary"></i> Equipo de Ventas
                            </button>
                            <button type="button" class="crm-subnav-btn" data-cfg-tab="fiscal">
                                <i class="fas fa-file-invoice mr-1 text-indigo"></i> Cotizaciones & Moneda
                            </button>
                        </div>
                    </div>

                    <div class="modal-body p-4" style="max-height: 74vh; overflow-y: auto;">
                        <!-- PESTAÑA 1: Mi Negocio & WhatsApp -->
                        <div id="cfg-pane-general">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    {{-- <h6 class="font-weight-bold text-dark mb-1">
                                        <i class="fab fa-whatsapp text-success mr-1"></i> Perfil Comercial y Asistente WhatsApp
                                    </h6> --}}
                                    <p class="small text-muted mb-0">Define los datos principales de tu negocio y el comportamiento del bot al intervenir un asesor.</p>
                                </div>
                                <span class="badge badge-light border text-muted px-2 py-1 font-mono small">
                                    Instancia #{{ $instancia->id }}
                                </span>
                            </div>

                            <form id="formAjustesInstancia" action="{{ route('instancias.update', $instancia->id) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="card border mb-3" style="border-radius: 12px; background: #ffffff;">
                                    <div class="card-body p-3">
                                        <div class="row">
                                            <div class="col-md-5 form-group mb-3 mb-md-0">
                                                <label class="saas-label font-weight-bold small text-dark mb-1">Nombre Comercial de la Empresa *</label>
                                                <input type="text" name="nombre_empresa" class="saas-input" value="{{ $instancia->nombre_empresa }}" required placeholder="Ej: Mi Empresa Guatemala">
                                                <span class="small text-muted" style="font-size: 0.74rem;">Visible en presupuestos, reportes y cotizaciones PDF.</span>
                                            </div>
                                            <div class="col-md-4 form-group mb-3 mb-md-0">
                                                <label class="saas-label font-weight-bold small text-dark mb-1">Teléfono WhatsApp Oficial</label>
                                                <input type="text" name="telefono" class="saas-input font-mono" value="{{ $instancia->telefono }}" placeholder="+502 xxxx xxxx">
                                                <span class="small text-muted" style="font-size: 0.74rem;">Línea de WhatsApp conectada para tus clientes.</span>
                                            </div>
                                            <div class="col-md-3 form-group mb-0">
                                                <label class="saas-label font-weight-bold small text-dark mb-1">Pausa al Intervenir Asesor</label>
                                                <div class="input-group">
                                                    <input type="number" name="bot_pause_hours" min="1" max="720" class="saas-input font-mono" value="{{ $instancia->bot_pause_hours ?? 12 }}" required>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text small bg-white text-muted border-left-0">horas</span>
                                                    </div>
                                                </div>
                                                <span class="small text-muted" style="font-size: 0.74rem;">El bot descansará este tiempo al responder un asesor humano.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Credenciales de Meta Cloud API (Agrupadas de forma ordenada) -->
                                <div class="card border mb-3" style="border-radius: 12px; background: #f8fafc;">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2" data-toggle="collapse" data-target="#collapseMetaCredentials" style="cursor: pointer;">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fas fa-shield-alt text-primary"></i>
                                                <span class="font-weight-bold text-dark small">Credenciales de Conexión Meta WhatsApp Cloud API</span>

                                            </div>
                                            <i class="fas fa-chevron-down text-muted small"></i>
                                        </div>

                                        <div class="collapse show" id="collapseMetaCredentials">
                                            <p class="small text-muted mb-3" style="font-size: 0.78rem;">
                                                Identificadores técnicos asignados por Meta para la sincronización de mensajes entrantes y salientes.
                                            </p>
                                            <div class="row">
                                                <div class="col-md-6 form-group mb-2">
                                                    <label class="saas-label small text-muted font-weight-bold">Phone Number ID (Meta) *</label>
                                                    <input type="text" name="phone_number_id" class="saas-input font-mono small" value="{{ $instancia->phone_number_id }}" required>
                                                </div>
                                                <div class="col-md-6 form-group mb-2">
                                                    <label class="saas-label small text-muted font-weight-bold">WABA ID (WhatsApp Business Account)</label>
                                                    <input type="text" name="waba_id" class="saas-input font-mono small" value="{{ $instancia->waba_id }}">
                                                </div>
                                                <div class="col-12 form-group mb-2">
                                                    <label class="saas-label small text-muted font-weight-bold">Token de Acceso de Meta (Bearer Token)</label>
                                                    <input type="password" name="access_token" class="saas-input font-mono small" placeholder="EAA..." value="{{ $instancia->access_token }}">
                                                    <span class="small text-muted" style="font-size: 0.72rem;">Token generado en Meta for Developers (Paso 1).</span>
                                                </div>
                                            </div>

                                            <div class="p-2 mt-2 rounded bg-white border d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                {{-- <div class="small text-muted font-mono" style="font-size: 0.75rem;">
                                                    <span class="text-dark font-weight-bold">Webhook URL para Meta:</span> <code>{{ url('/api/webhook') }}</code>
                                                </div> --}}
                                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="navigator.clipboard.writeText('{{ url('/api/webhook') }}'); alert('¡URL de webhook copiada al portapapeles!');">
                                                    <i class="fas fa-copy mr-1"></i> Copiar URL
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-3">
                                    <button type="submit" id="btnGuardarAjustes" class="btn-saas btn-saas-primary btn-sm px-4">
                                        <i class="fas fa-save mr-1"></i> Guardar Ajustes del Negocio
                                    </button>
                                </div>
                            </form>

                            <!-- Opciones Técnicas de Integración (Colapsadas para evitar ruido comercial) -->
                            {{-- <div class="mt-4 pt-3 border-top">
                                <div class="d-flex justify-content-between align-items-center py-2" data-toggle="collapse" data-target="#collapseAvanzadoDev" style="cursor: pointer;">
                                    <span class="small font-weight-bold text-muted">
                                        <i class="fas fa-code mr-1 text-secondary"></i> Herramientas de Integración API REST & Webhooks (Opcional)
                                    </span>
                                    <i class="fas fa-chevron-down text-muted small"></i>
                                </div>

                                <div class="collapse mt-3" id="collapseAvanzadoDev">
                                    <!-- Token API REST -->
                                    <div class="p-3 mb-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
                                        <h6 class="font-weight-bold text-dark small mb-1"><i class="fas fa-key text-warning mr-1"></i> Token API REST de la Empresa</h6>
                                        <p class="small text-muted mb-2" style="font-size: 0.75rem;">Úsalo si conectas tu ERP externo o formularios web con la API REST.</p>
                                        <div class="input-group mb-2">
                                            <input type="text" id="cfgApiTokenInput" class="saas-input font-mono small" readonly value="{{ $apiToken ?: 'No generado aún' }}">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnCopyApiToken"><i class="fas fa-copy"></i> Copiar</button>
                                                <button type="button" class="btn btn-outline-danger btn-sm" id="btnRegenerateApiToken"><i class="fas fa-sync"></i> Regenerar</button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Formulario Webhook Saliente -->
                                    <form id="formConfigWebhook" class="p-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
                                        <label class="small font-weight-bold text-dark mb-2 d-flex align-items-center gap-2" style="cursor: pointer;">
                                            <input type="checkbox" id="cfgWebhookActivo" {{ !empty($webhookSaliente['activo']) ? 'checked' : '' }}>
                                            <span>Activar Notificaciones Webhook Salientes</span>
                                        </label>
                                        <div class="row">
                                            <div class="col-md-7 form-group mb-2">
                                                <label class="saas-label small">URL Endpoint Receptor HTTP POST</label>
                                                <input type="url" id="cfgWebhookUrl" class="saas-input font-mono small" placeholder="https://api.tuempresa.com/webhook" value="{{ $webhookSaliente['url'] ?? '' }}">
                                            </div>
                                            <div class="col-md-5 form-group mb-2">
                                                <label class="saas-label small">Firma Secreta HMAC</label>
                                                <input type="text" id="cfgWebhookSecret" class="saas-input font-mono small" placeholder="whsec_xxxxxx" value="{{ $webhookSaliente['secret_key'] ?? '' }}">
                                            </div>
                                        </div>
                                        @php
                                            $evs = $webhookSaliente['eventos'] ?? ['lead.creado', 'lead.etapa_cambiada'];
                                        @endphp
                                        <div class="d-flex flex-wrap gap-3 small text-secondary mb-2">
                                            <label style="cursor: pointer;" class="d-flex align-items-center gap-1">
                                                <input type="checkbox" class="cfg-webhook-event" value="lead.creado" {{ in_array('lead.creado', $evs) ? 'checked' : '' }}>
                                                <span>lead.creado</span>
                                            </label>
                                            <label style="cursor: pointer;" class="d-flex align-items-center gap-1">
                                                <input type="checkbox" class="cfg-webhook-event" value="lead.etapa_cambiada" {{ in_array('lead.etapa_cambiada', $evs) ? 'checked' : '' }}>
                                                <span>lead.etapa_cambiada</span>
                                            </label>
                                            <label style="cursor: pointer;" class="d-flex align-items-center gap-1">
                                                <input type="checkbox" class="cfg-webhook-event" value="lead.asignado" {{ in_array('lead.asignado', $evs) ? 'checked' : '' }}>
                                                <span>lead.asignado</span>
                                            </label>
                                        </div>
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn-saas btn-saas-secondary btn-sm">
                                                <i class="fas fa-save mr-1"></i> Guardar Webhook
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div> --}}
                        </div>

                        <!-- PESTAÑA 2: Horarios de Atención (Guatemala) -->
                        <div id="cfg-pane-horarios" class="d-none">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="font-weight-bold text-dark mb-1">
                                        <i class="fas fa-clock text-warning mr-1"></i> Horarios Comerciales y Respuesta Fuera de Oficina
                                    </h6>
                                    <p class="small text-muted mb-0">Configura la jornada de atención en Guatemala para enviar mensajes automáticos a clientes fuera de hora.</p>
                                </div>
                                <span class="badge badge-light border text-dark font-weight-bold px-2 py-1 font-mono small">
                                    America/Guatemala (GMT-6)
                                </span>
                            </div>

                            <form id="formConfigHorario">
                                <input type="hidden" id="cfgHorarioTimezone" value="America/Guatemala">

                                <div class="p-3 mb-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
                                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                        <label class="font-weight-bold text-dark mb-0 d-flex align-items-center gap-2" style="cursor: pointer;">
                                            <input type="checkbox" id="cfgHorarioActivo" {{ !empty($horarioAtencion['activo']) ? 'checked' : '' }} style="width: 17px; height: 17px;">
                                            <span>Activar Respuesta Automática Fuera de Horario</span>
                                        </label>
                                        <button type="button" id="btnCopiarHorarioSemana" class="btn btn-xs btn-outline-primary">
                                            <i class="fas fa-copy mr-1"></i> Copiar Lunes a toda la semana (L-V)
                                        </button>
                                    </div>

                                    @php
                                        $diasSemana = [
                                            'lunes'     => 'Lunes',
                                            'martes'    => 'Martes',
                                            'miercoles' => 'Miércoles',
                                            'jueves'    => 'Jueves',
                                            'viernes'   => 'Viernes',
                                            'sabado'    => 'Sábado',
                                            'domingo'   => 'Domingo',
                                        ];
                                    @endphp

                                    <div class="d-flex flex-column gap-2">
                                        @foreach($diasSemana as $dKey => $dNom)
                                            @php
                                                $dConf = $horarioAtencion['dias'][$dKey] ?? [
                                                    'activo' => ($dKey !== 'domingo'),
                                                    'desde'  => ($dKey === 'sabado' ? '08:30' : '08:00'),
                                                    'hasta'  => ($dKey === 'sabado' ? '13:00' : '18:00')
                                                ];
                                            @endphp
                                            <div class="d-flex align-items-center justify-content-between p-2 rounded bg-white border">
                                                <div class="d-flex align-items-center gap-3" style="min-width: 140px;">
                                                    <label class="mb-0 d-flex align-items-center gap-2" style="cursor: pointer;">
                                                        <input type="checkbox" class="cfg-day-active" data-day="{{ $dKey }}" {{ !empty($dConf['activo']) ? 'checked' : '' }}>
                                                        <span class="font-weight-bold small text-dark">{{ $dNom }}</span>
                                                    </label>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="small text-muted">Apertura:</span>
                                                    <input type="time" class="saas-input py-0 px-2 cfg-day-start font-mono" data-day="{{ $dKey }}" style="width: 110px; height: 30px; font-size: 0.8rem;" value="{{ $dConf['desde'] ?? '08:00' }}">
                                                    <span class="small text-muted mx-1">Cierre:</span>
                                                    <input type="time" class="saas-input py-0 px-2 cfg-day-end font-mono" data-day="{{ $dKey }}" style="width: 110px; height: 30px; font-size: 0.8rem;" value="{{ $dConf['hasta'] ?? '18:00' }}">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="saas-label font-weight-bold small text-dark mb-0">Mensaje Automático de Ausencia por WhatsApp</label>
                                        <span class="small text-muted" style="font-size: 0.74rem;">Se envía cuando el cliente escribe fuera de tu jornada laboral</span>
                                    </div>
                                    <textarea id="cfgHorarioMensaje" class="saas-input p-3" rows="3" placeholder="Mensaje cordial que recibirá el cliente...">{{ $horarioAtencion['mensaje_fuera_horario'] ?? '¡Hola! Gracias por comunicarte con nosotros. En este momento nos encontramos fuera de horario de atención comercial. Te responderemos el siguiente día hábil a primera hora.' }}</textarea>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn-saas btn-saas-primary btn-sm px-4">
                                        <i class="fas fa-save mr-1"></i> Guardar Horario Comercial
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- PESTAÑA 3: Equipo de Ventas & Asesores -->
                        <div id="cfg-pane-usuarios" class="d-none">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="font-weight-bold text-dark mb-1">
                                        <i class="fas fa-users text-primary mr-1"></i> Equipo de Ventas y Control de Accesos
                                    </h6>
                                    <p class="small text-muted mb-0">Administra los asesores que atienden chats y gestionan oportunidades en <strong>{{ $instancia->nombre_empresa }}</strong>.</p>
                                </div>
                            </div>

                            @if($usuariosEmpresa && $usuariosEmpresa->count() > 0)
                                <div class="table-responsive mb-4 border rounded" style="background:#ffffff;">
                                    <table class="table table-hover mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="border-0" style="min-width: 180px;">Asesor / Usuario</th>
                                                <th class="border-0" style="min-width: 240px;">Correo de Acceso</th>
                                                <th class="border-0" style="min-width: 120px;">Rol</th>
                                                <th class="border-0" style="min-width: 180px;">Visibilidad de Leads</th>
                                                <th class="border-0 text-right" style="min-width: 100px;">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($usuariosEmpresa as $uEmp)
                                                <tr>
                                                    <td class="font-weight-600 align-middle">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="crm-avatar" style="width: 28px; height: 28px; background: #e0e7ff; color: #4338ca; font-size: 0.75rem;">
                                                                {{ strtoupper(substr($uEmp->name, 0, 2)) }}
                                                            </div>
                                                            <span>{{ $uEmp->name }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="font-mono small align-middle text-muted">{{ $uEmp->email }}</td>
                                                    <td class="align-middle">
                                                        <span class="badge {{ ($uEmp->sub_rol ?? 'vendedor') === 'supervisor' ? 'badge-primary' : 'badge-light border' }}">
                                                            {{ ($uEmp->sub_rol ?? 'vendedor') === 'supervisor' ? 'Supervisor' : 'Vendedor' }}
                                                        </span>
                                                    </td>
                                                    <td class="small align-middle">
                                                        <span class="text-muted">
                                                            <i class="fas {{ $uEmp->solo_asignados ? 'fa-lock text-warning' : 'fa-globe text-secondary' }} mr-1"></i>
                                                            {{ $uEmp->solo_asignados ? 'Solo leads asignados' : 'Todos los leads' }}
                                                        </span>
                                                    </td>
                                                    <td class="text-right align-middle">
                                                        <form action="{{ route('instancias.usuarios.destroy', [$instancia->id, $uEmp->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas revocar el acceso a este asesor?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-xs btn-outline-danger">
                                                                <i class="fas fa-user-times mr-1"></i> Revocar
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            <form action="{{ route('instancias.usuarios.store', $instancia->id) }}" method="POST" class="p-3" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                                @csrf
                                <div class="font-weight-bold text-dark small mb-3">
                                    <i class="fas fa-user-plus text-primary mr-1"></i> Habilitar Acceso a Nuevo Asesor de Ventas
                                </div>
                                <div class="row">
                                    <div class="col-md-4 form-group mb-2">
                                        <label class="saas-label small text-muted">Nombre Completo *</label>
                                        <input type="text" name="name" class="saas-input" placeholder="Ej: Carlos Méndez" required>
                                    </div>
                                    <div class="col-md-4 form-group mb-2">
                                        <label class="saas-label small text-muted">Correo Electrónico (Login) *</label>
                                        <input type="email" name="email" class="saas-input" placeholder="carlos@tuempresa.gt" required>
                                    </div>
                                    <div class="col-md-4 form-group mb-2">
                                        <label class="saas-label small text-muted">Contraseña Provisional (mín. 8) *</label>
                                        <input type="password" name="password" class="saas-input" placeholder="••••••••" minlength="8" required>
                                    </div>
                                </div>
                                <div class="row align-items-center mt-2">
                                    <div class="col-md-4 form-group mb-2 mb-md-0">
                                        <label class="saas-label small text-muted">Rol en la Empresa</label>
                                        <select name="sub_rol" class="crm-topbar-select w-100">
                                            <option value="vendedor" selected>Vendedor / Asesor Comercial</option>
                                            <option value="supervisor">Supervisor de Equipo</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5 form-group mb-2 mb-md-0 pt-md-3">
                                        <label class="small text-secondary mb-0 d-flex align-items-center gap-2" style="cursor: pointer;">
                                            <input type="checkbox" name="solo_asignados" value="1" checked style="width: 16px; height: 16px;">
                                            <span class="font-weight-500">Restringir a ver únicamente sus propios leads asignados</span>
                                        </label>
                                    </div>
                                    <div class="col-md-3 d-flex justify-content-end pt-md-3">
                                        <button type="submit" class="btn-saas btn-saas-primary btn-sm px-3">
                                            <i class="fas fa-plus-circle mr-1"></i> Habilitar Acceso
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- PESTAÑA 4: Cotizaciones & Moneda (Guatemala) -->
                        <div id="cfg-pane-fiscal" class="d-none">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="font-weight-bold text-dark mb-1">
                                        <i class="fas fa-file-invoice text-indigo mr-1"></i> Cotizaciones, Moneda e Impuestos (Guatemala)
                                    </h6>
                                    <p class="small text-muted mb-0">Parámetros aplicados al generar presupuestos formales para tus prospectos por WhatsApp o PDF.</p>
                                </div>
                                <span class="badge badge-light border text-dark font-weight-bold px-2 py-1 small">
                                    IVA General: 12% SAT
                                </span>
                            </div>

                            <form id="formConfigFiscal">
                                <div class="p-3 mb-3" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px;">
                                    <div class="row">
                                        <div class="col-md-4 form-group mb-3">
                                            <label class="saas-label font-weight-bold small text-dark mb-1">Moneda Principal</label>
                                            <select id="cfgFiscalMoneda" class="crm-topbar-select font-mono w-100">
                                                <option value="GTQ" {{ ($datosFiscales['moneda'] ?? 'GTQ') === 'GTQ' ? 'selected' : '' }}>GTQ (Quetzales - Q)</option>
                                                <option value="USD" {{ ($datosFiscales['moneda'] ?? '') === 'USD' ? 'selected' : '' }}>USD (Dólares - $)</option>
                                            </select>
                                            <span class="small text-muted" style="font-size: 0.74rem;">Moneda base para montos de productos y cotizaciones.</span>
                                        </div>
                                        <div class="col-md-4 form-group mb-3">
                                            <label class="saas-label font-weight-bold small text-dark mb-1">Porcentaje de Impuesto (% IVA SAT)</label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" max="100" id="cfgFiscalImpuesto" class="saas-input font-mono" value="{{ $datosFiscales['porcentaje_impuesto'] ?? 12 }}">
                                                <div class="input-group-append">
                                                    <span class="input-group-text small bg-white text-muted border-left-0">%</span>
                                                </div>
                                            </div>
                                            <span class="small text-muted" style="font-size: 0.74rem;">Tasa general de IVA en Guatemala (12.00%).</span>
                                        </div>
                                        <div class="col-md-4 form-group mb-3">
                                            <label class="saas-label font-weight-bold small text-dark mb-1">Validez por Defecto</label>
                                            <div class="input-group">
                                                <input type="number" min="1" max="365" id="cfgFiscalValidez" class="saas-input font-mono" value="{{ $datosFiscales['validez_dias_default'] ?? 15 }}">
                                                <div class="input-group-append">
                                                    <span class="input-group-text small bg-white text-muted border-left-0">días</span>
                                                </div>
                                            </div>
                                            <span class="small text-muted" style="font-size: 0.74rem;">Vigencia del presupuesto antes de expirar.</span>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 form-group mb-3">
                                            <label class="saas-label font-weight-bold small text-dark mb-1">Nombre Comercial o Razón Social en Cotizaciones *</label>
                                            <input type="text" id="cfgFiscalRazon" class="saas-input" value="{{ $datosFiscales['razon_social'] ?? $instancia->nombre_empresa }}" required placeholder="Ej: Mi Negocio, S.A.">
                                        </div>
                                        <div class="col-md-6 form-group mb-3">
                                            <label class="saas-label font-weight-bold small text-dark mb-1">NIT de la Empresa (SAT Guatemala)</label>
                                            <input type="text" id="cfgFiscalNit" class="saas-input font-mono" value="{{ $datosFiscales['nit_rfc'] ?? '' }}" placeholder="Ej: 12345678-9 o C/F">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-7 form-group mb-2">
                                            <label class="saas-label font-weight-bold small text-dark mb-1">Dirección Comercial o Sucursal (Opcional)</label>
                                            <input type="text" id="cfgFiscalDireccion" class="saas-input" value="{{ $datosFiscales['direccion'] ?? '' }}" placeholder="Ej: Ciudad de Guatemala">
                                        </div>
                                        <div class="col-md-5 form-group mb-2">
                                            <label class="saas-label font-weight-bold small text-dark mb-1">Correo para Cotizaciones (Opcional)</label>
                                            <input type="email" id="cfgFiscalEmail" class="saas-input" value="{{ $datosFiscales['email_contacto'] ?? '' }}" placeholder="ventas@tuempresa.gt">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="saas-label font-weight-bold small text-dark mb-0">Términos, Condiciones y Notas al Pie de Cotización</label>
                                        <span class="small text-muted" style="font-size: 0.74rem;">Texto visible al final del presupuesto o mensaje de WhatsApp</span>
                                    </div>
                                    <textarea id="cfgFiscalTerminos" class="saas-input p-3" rows="3">{{ $datosFiscales['terminos_condiciones'] ?? 'Precios expresados en Quetzales e incluyen el 12% de IVA. Esta cotización tiene validez de 15 días calendario a partir de su emisión.' }}</textarea>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn-saas btn-saas-primary btn-sm px-4">
                                        <i class="fas fa-save mr-1"></i> Guardar Configuración de Cotizaciones
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Nueva Tarea / Evento de Calendario (Estilo Google Calendar) -->
    <div class="modal fade" id="modalCalendarCreateTask" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg border-0" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header bg-light border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="modal-title font-weight-bold text-dark mb-0" style="font-size: 1.05rem;">
                        <i class="fas fa-calendar-plus text-primary mr-2"></i>Nueva Tarea / Evento
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.2rem;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="formCalendarCreateTask">
                    <div class="modal-body p-4">
                        <div class="form-group mb-3">
                            <label class="saas-label small font-weight-bold mb-1">Título de la tarea o recordatorio *</label>
                            <input type="text" id="cal-task-title" class="saas-input" placeholder="Ej. Llamar para confirmar cotización..." required>
                        </div>

                        <div class="form-group mb-3">
                            <label class="saas-label small font-weight-bold mb-1">Lead / Cliente Asociado *</label>
                            <select id="cal-task-lead-select" class="saas-input" required>
                                <option value="">-- Seleccionar Lead --</option>
                            </select>
                        </div>

                        <div class="row no-gutters mb-3">
                            <div class="col-6 pr-2">
                                <label class="saas-label small font-weight-bold mb-1">Tipo de Tarea</label>
                                <select id="cal-task-type" class="saas-input">
                                    <option value="seguimiento">Seguimiento</option>
                                    <option value="llamada">Llamada telefónica</option>
                                    <option value="reunion">Reunión / Cita</option>
                                    <option value="cotizacion">Enviar Cotización</option>
                                </select>
                            </div>
                            <div class="col-6 pl-2">
                                <label class="saas-label small font-weight-bold mb-1">Fecha Programada *</label>
                                <input type="date" id="cal-task-date" class="saas-input" required>
                            </div>
                        </div>

                        <div class="row no-gutters mb-1">
                            <div class="col-6 pr-2">
                                <label class="saas-label small font-weight-bold mb-1">Hora Opcional</label>
                                <input type="time" id="cal-task-time" class="saas-input">
                            </div>
                            <div class="col-6 pl-2">
                                <label class="saas-label small font-weight-bold mb-1">Plazo descriptivo</label>
                                <input type="text" id="cal-task-due-desc" class="saas-input" placeholder="Ej. Por la mañana / Urgente">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 py-2 px-4 d-flex justify-content-between">
                        <button type="button" class="btn btn-sm btn-light text-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-saas btn-saas-primary px-3 py-1">
                            <i class="fas fa-check mr-1"></i> Guardar Tarea
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detalle de Tarea (Estilo Google Calendar) -->
    <div class="modal fade" id="modalCalendarTaskDetail" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg border-0" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <span class="badge" id="cal-detail-type-badge" style="font-size: 0.8rem; padding: 4px 8px;">Seguimiento</span>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.2rem;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body px-4 pt-1 pb-3">
                    <h4 class="font-weight-bold text-dark mb-2" id="cal-detail-title">Título de la tarea</h4>
                    
                    <div class="d-flex align-items-center gap-2 mb-3 text-secondary" style="font-size: 0.88rem;">
                        <i class="far fa-clock text-primary"></i>
                        <span id="cal-detail-date">30 de Octubre de 2026</span>
                    </div>

                    <div class="p-3 rounded bg-light border mb-3">
                        <div class="small text-muted mb-1 font-weight-bold text-uppercase" style="letter-spacing: 0.04em;">Cliente asociado:</div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="text-dark" id="cal-detail-lead-name">Nombre Cliente</strong>
                                <div class="small text-muted font-mono" id="cal-detail-lead-phone">+502 00000000</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnCalDetailJumpChat">
                                <i class="fab fa-whatsapp mr-1"></i> Abrir Chat
                            </button>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                        <input type="checkbox" id="cal-detail-toggle-done" style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="cal-detail-toggle-done" class="mb-0 text-dark font-weight-500" style="cursor: pointer; font-size: 0.88rem;">
                            Marcar como tarea completada
                        </label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnCalDetailDelete">
                        <i class="fas fa-trash-alt mr-1"></i> Eliminar
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Datos y configuración para los scripts del CRM -->
    <div id="blueprint-config" 
         class="blueprint-config-data"
         data-csrf-token="{{ csrf_token() }}" 
         data-store-node-url="{{ route('chatbot.node.store') }}" 
         data-store-option-url="{{ route('chatbot.option.store') }}" 
         data-upload-media-url="{{ route('chatbot.node.upload_media') }}" 
         data-instancia-id="{{ $instancia->id }}"></div>

    <script id="chatbot-nodes-json" type="application/json">@json($nodes)</script>
    <script id="crm-leads-json" type="application/json">@json($todosContactos)</script>
    <script id="crm-stages-json" type="application/json">@json($etapas)</script>
    <script id="crm-catalog-json" type="application/json">@json($catalogoProductos)</script>
    <script id="crm-templates-json" type="application/json">@json($plantillasRapidas)</script>
    <script id="crm-automations-json" type="application/json">@json($pipelineAutomations)</script>
    <script id="crm-broadcasts-json" type="application/json">@json($campanasDifusion)</script>
    <script id="crm-custom-fields-json" type="application/json">@json($camposPersonalizadosDef)</script>
    <script id="crm-advisors-json" type="application/json">@json($usuariosEmpresa)</script>
    <script id="crm-horario-json" type="application/json">@json($horarioAtencion)</script>
    <script id="crm-base-conocimiento-json" type="application/json">@json($baseConocimiento)</script>
    <script id="crm-webhook-json" type="application/json">@json($webhookSaliente)</script>
    <script id="crm-fiscal-json" type="application/json">@json($datosFiscales)</script>
    <script id="crm-plan-json" type="application/json">@json($planInfo)</script>
    <script id="crm-api-token" type="text/plain">{{ $apiToken }}</script>
@stop

@section('js')
    <script src="{{ asset('js/floating-navbar.js') }}?v={{ file_exists(public_path('js/floating-navbar.js')) ? filemtime(public_path('js/floating-navbar.js')) : time() }}"></script>
    <script src="{{ asset('js/crm-suite.js') }}?v={{ file_exists(public_path('js/crm-suite.js')) ? filemtime(public_path('js/crm-suite.js')) : time() }}"></script>
    @if($isAdmin)
        <script src="{{ asset('js/prompt-simulator.js') }}?v={{ file_exists(public_path('js/prompt-simulator.js')) ? filemtime(public_path('js/prompt-simulator.js')) : time() }}"></script>
        <script src="{{ asset('js/blueprint-drawer.js') }}?v={{ file_exists(public_path('js/blueprint-drawer.js')) ? filemtime(public_path('js/blueprint-drawer.js')) : time() }}"></script>
        <script src="{{ asset('js/blueprint-builder.js') }}?v={{ file_exists(public_path('js/blueprint-builder.js')) ? filemtime(public_path('js/blueprint-builder.js')) : time() }}"></script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const formAjustes = document.getElementById('formAjustesInstancia');
            const btnGuardar = document.getElementById('btnGuardarAjustes');
            if (formAjustes && btnGuardar) {
                const inputs = formAjustes.querySelectorAll('input:not([type="hidden"])');
                const initialValues = {};
                inputs.forEach(input => { initialValues[input.name] = input.value.trim(); });
                function checkFormChanges() {
                    let hasChanges = false;
                    inputs.forEach(input => {
                        if (input.value.trim() !== (initialValues[input.name] ?? '')) hasChanges = true;
                    });
                    btnGuardar.disabled = !hasChanges;
                }
                inputs.forEach(input => {
                    input.addEventListener('input', checkFormChanges);
                    input.addEventListener('change', checkFormChanges);
                    input.addEventListener('keyup', checkFormChanges);
                });
            }

            const promptTextarea = document.getElementById('live_system_prompt');
            const btnGuardarPrompt = document.getElementById('btnGuardarPrompt');
            if (promptTextarea && btnGuardarPrompt) {
                const initialPrompt = promptTextarea.value;
                function checkPromptChanges() {
                    btnGuardarPrompt.disabled = (promptTextarea.value === initialPrompt);
                }
                promptTextarea.addEventListener('input', checkPromptChanges);
                promptTextarea.addEventListener('change', checkPromptChanges);
                promptTextarea.addEventListener('keyup', checkPromptChanges);
            }
        });
    </script>
@stop
