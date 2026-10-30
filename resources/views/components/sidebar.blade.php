<!-- Barra lateral de navegación -->
<aside class="app-sidebar">
    {{-- <div class="sidebar-brand-wrapper">
        <div class="sidebar-brand">
            <span class="brand-title">PrimeCRM</span>
        </div>
    </div> --}}

    <nav class="sidebar-nav">
        @if(request()->routeIs('instancias.show') && isset($instancia) && is_object($instancia) && isset($instancia->id))
            @if(Auth::user()?->isAdmin())
                <a href="{{ route('instancias.index') }}" class="nav-item nav-item-back" title="Volver al Hub de Instancias">
                    <i class="fas fa-arrow-left"></i>
                    <span>Todas las Empresas</span>
                </a>
                <div class="nav-divider"></div>
            @endif

            <div class="sidebar-category-label">OPERACIÓN COMERCIAL</div>

            <a href="#dashboard" class="nav-item sidebar-tab-link active" data-nav="dashboard" title="Dashboard y Analítica en Vivo">
                <div class="nav-icon-wrap"><i class="fas fa-chart-pie"></i></div>
                <span>Dashboard</span>
            </a>

            <a href="#pipeline" class="nav-item sidebar-tab-link" data-nav="pipeline" title="Embudo Digital de Ventas (Kanban)">
                <div class="nav-icon-wrap"><i class="fas fa-columns"></i></div>
                <span>Embudo</span>
            </a>

            <a href="#chats" class="nav-item sidebar-tab-link" data-nav="chats" title="Bandeja de Entrada Omnicanal">
                <div class="nav-icon-wrap"><i class="fab fa-whatsapp"></i></div>
                <span>Chats</span>
            </a>

            <a href="#tareas" class="nav-item sidebar-tab-link" data-nav="tareas" title="Centro de Tareas y Agenda To-Do">
                <div class="nav-icon-wrap"><i class="fas fa-calendar-check"></i></div>
                <span>Tareas</span>
            </a>

            <a href="#listas" class="nav-item sidebar-tab-link" data-nav="listas" title="Contactos, Catálogo y Difusiones">
                <div class="nav-icon-wrap"><i class="fas fa-address-book"></i></div>
                <span>Listas</span>
            </a>

            @if(Auth::user()?->isAdmin())
                <div class="sidebar-category-label mt-3">IA & AUTOMATIZACIÓN</div>

                <a href="#nodos" class="nav-item sidebar-tab-link" data-nav="nodos" title="Constructor Visual de Flujos">
                    <div class="nav-icon-wrap"><i class="fas fa-project-diagram"></i></div>
                    <span>Flujos Bot</span>
                </a>

                <a href="#prompt" class="nav-item sidebar-tab-link" data-nav="prompt" title="Entrenamiento e Instrucciones IA">
                    <div class="nav-icon-wrap"><i class="fas fa-robot"></i></div>
                    <span>Agente IA</span>
                </a>

                <div class="sidebar-category-label mt-3">EMPRESA</div>

                <a href="#modalConfigTecnica" class="nav-item" data-toggle="modal" title="Credenciales Meta, Horarios y Usuarios"> 
                    <div class="nav-icon-wrap"><i class="fas fa-sliders-h"></i></div>
                    <span>Ajustes</span>
                </a>
            @endif
        @else
            <a href="{{ route('instancias.index') }}" class="nav-item {{ request()->routeIs('instancias.index') ? 'active' : '' }}">
                <div class="nav-icon-wrap"><i class="fas fa-layer-group"></i></div>
                <span>Instancias Activas</span>
            </a>
        @endif
    </nav>

    <div class="sidebar-user-card">
        <div class="user-avatar-gradient">
            {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
        </div>
        <div class="user-meta">
            <span class="user-name" title="{{ Auth::user()->name ?? 'Usuario' }}">{{ Auth::user()->name ?? 'Usuario' }}</span>
            <span class="user-role-badge">
                {{ Auth::user()?->isAdmin() ? 'Super Admin' : ucfirst(Auth::user()?->sub_rol ?? 'Asesor') }}
            </span>
        </div>
        <form action="{{ route('logout') }}" method="POST" class="d-inline ml-auto">
            @csrf
            <button type="submit" class="btn-sidebar-logout" title="Cerrar sesión">
                <i class="fas fa-arrow-right-from-bracket"></i>
            </button>
        </form>
    </div>
</aside>
