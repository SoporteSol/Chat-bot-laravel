// Barra de herramientas flotante central y cambio de vistas en el panel de instancia (Vistas en Sidebar, Opciones en Centro)
document.addEventListener('DOMContentLoaded', function () {
    const configEl = document.getElementById('blueprint-config');
    const instanciaId = configEl?.getAttribute('data-instancia-id') || '';

    const sidebarLinks = document.querySelectorAll('.sidebar-tab-link');
    const contextGroups = document.querySelectorAll('.floating-context-group[data-view-tools]');

    const indicatorLabel = document.getElementById('currentViewTitle');
    const indicatorIcon = document.getElementById('currentViewIcon');

    const viewMeta = {
        'dashboard': { label: 'Dashboard', icon: 'fas fa-chart-pie' },
        'pipeline':  { label: 'Embudo',    icon: 'fas fa-columns' },
        'chats':     { label: 'Chats',     icon: 'fab fa-whatsapp' },
        'tareas':    { label: 'Tareas',    icon: 'fas fa-calendar-check' },
        'listas':    { label: 'Listas',    icon: 'fas fa-address-book' },
        'nodos':     { label: 'Flujos Bot',icon: 'fas fa-project-diagram' },
        'prompt':    { label: 'Agente IA', icon: 'fas fa-robot' }
    };

    const panes = {
        'dashboard': document.getElementById('pane-dashboard'),
        'pipeline': document.getElementById('pane-pipeline'),
        'chats': document.getElementById('pane-chats'),
        'tareas': document.getElementById('pane-tareas'),
        'listas': document.getElementById('pane-listas'),
        'nodos': document.getElementById('pane-nodos'),
        'prompt': document.getElementById('pane-prompt')
    };

    function switchTab(targetKey) {
        let normalized = 'dashboard';
        const str = (targetKey || '').toLowerCase();
        if (str.includes('dashboard') || str.includes('estadistica') || str.includes('analitica')) {
            normalized = 'dashboard';
        } else if (str.includes('pipeline') || str.includes('embudo') || str.includes('kanban') || str.includes('leads')) {
            normalized = 'pipeline';
        } else if (str.includes('chat') || str.includes('inbox') || str.includes('bandeja')) {
            normalized = 'chats';
        } else if (str.includes('tarea') || str.includes('calendario') || str.includes('todo')) {
            normalized = 'tareas';
        } else if (str.includes('lista') || str.includes('catalogo') || str.includes('difusion') || str.includes('contacto')) {
            normalized = 'listas';
        } else if (str.includes('nodo') || str.includes('respuesta') || str.includes('personalizar') || str.includes('blueprint') || str.includes('flujo')) {
            normalized = 'nodos';
        } else if (str.includes('prompt') || str.includes('rol') || str.includes('entrenamiento')) {
            normalized = 'prompt';
        }

        if (!panes[normalized]) {
            normalized = panes['dashboard'] ? 'dashboard' : 'pipeline';
        }

        // 1. Marcamos la pestaña activa en la barra lateral (Sidebar: Navegador exclusivo de vistas)
        sidebarLinks.forEach(item => {
            const attr = (item.getAttribute('data-nav') || '').toLowerCase();
            item.classList.toggle('active', attr === normalized);
        });

        // 2. Actualizamos el título e icono de la vista activa en la barra central
        if (viewMeta[normalized]) {
            if (indicatorLabel) indicatorLabel.textContent = viewMeta[normalized].label;
            if (indicatorIcon) indicatorIcon.className = 'view-indicator-icon ' + viewMeta[normalized].icon + ' text-primary';
        }

        // 3. Mostramos las opciones y herramientas de la vista seleccionada en la barra central
        contextGroups.forEach(group => {
            const viewKey = (group.getAttribute('data-view-tools') || '').toLowerCase();
            group.classList.toggle('d-none', viewKey !== normalized);
        });

        // 4. Activamos el contenedor de la vista
        Object.keys(panes).forEach(k => {
            if (panes[k]) {
                panes[k].classList.toggle('active', k === normalized);
            }
        });

        try {
            if (instanciaId) {
                sessionStorage.setItem('instancia_tab_' + instanciaId, normalized);
            }
            history.replaceState(null, null, '#' + normalized);
        } catch (e) { }

        if (normalized === 'nodos') {
            setTimeout(function () {
                if (window.BlueprintCanvas) {
                    if (typeof window.BlueprintCanvas.render === 'function') window.BlueprintCanvas.render();
                    if (typeof window.BlueprintCanvas.updateConnections === 'function') window.BlueprintCanvas.updateConnections();
                }
            }, 60);
        }

        if (window.CrmSuite && typeof window.CrmSuite.refreshAll === 'function') {
            window.CrmSuite.refreshAll();
        }
    }

    // La navegación entre vistas ocurre exclusivamente al pulsar en el Sidebar lateral
    sidebarLinks.forEach(item => {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            switchTab(this.getAttribute('data-nav') || this.textContent);
        });
    });

    // Acciones y opciones de la vista activa (Barra flotante central)

    // Dashboard: filtrar KPIs, embudo o actividad
    const dashButtons = document.querySelectorAll('[data-dash-view]');
    dashButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            dashButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const mode = this.getAttribute('data-dash-view') || 'all';

            const kpisEl = document.getElementById('dash-section-kpis');
            const funnelEl = document.getElementById('dash-section-funnel');
            const activityEl = document.getElementById('dash-section-activity');
            const leadsEl = document.getElementById('dash-section-leads');

            if (kpisEl) kpisEl.classList.toggle('d-none', mode !== 'all' && mode !== 'kpis');
            if (funnelEl) {
                funnelEl.classList.toggle('d-none', mode !== 'all' && mode !== 'funnel');
                funnelEl.className = (mode === 'funnel') ? 'col-lg-12 mb-4' : 'col-lg-7 mb-4';
            }
            if (activityEl) {
                activityEl.classList.toggle('d-none', mode !== 'all' && mode !== 'activity');
                activityEl.className = (mode === 'activity') ? 'col-lg-12 mb-4' : 'col-lg-5 mb-4';
            }
            if (leadsEl) {
                leadsEl.classList.toggle('d-none', mode !== 'all');
            }
        });
    });

    // Embudo: filtro rápido bot / humano / todos
    const pipelineFilterButtons = document.querySelectorAll('[data-pipeline-filter]');
    const selectPipelineBotFilter = document.getElementById('selectPipelineBotFilter');
    pipelineFilterButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            pipelineFilterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const val = this.getAttribute('data-pipeline-filter') || 'all';
            if (selectPipelineBotFilter) {
                selectPipelineBotFilter.value = val;
                selectPipelineBotFilter.dispatchEvent(new Event('change'));
            }
        });
    });
    selectPipelineBotFilter?.addEventListener('change', function () {
        const val = this.value;
        pipelineFilterButtons.forEach(b => {
            b.classList.toggle('active', b.getAttribute('data-pipeline-filter') === val);
        });
    });

    // Chats: filtros de bandeja
    const chatFilterButtons = document.querySelectorAll('[data-chat-filter]');
    chatFilterButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const filterKey = this.getAttribute('data-chat-filter') || 'all';
            chatFilterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const pill = document.querySelector(`.crm-filter-pill[data-filter="${filterKey}"]`);
            if (pill) pill.click();
        });
    });
    document.querySelectorAll('.crm-filter-pill').forEach(pill => {
        pill.addEventListener('click', function () {
            const f = this.getAttribute('data-filter') || 'all';
            chatFilterButtons.forEach(b => {
                b.classList.toggle('active', b.getAttribute('data-chat-filter') === f);
            });
        });
    });

    // Tareas: filtro pendientes/completadas y atajo para crear
    const taskViewButtons = document.querySelectorAll('[data-task-view]');
    taskViewButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const viewKey = this.getAttribute('data-task-view') || 'pendientes';
            taskViewButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const subBtn = document.querySelector(`[data-tasks-filter="${viewKey}"]`);
            if (subBtn) subBtn.click();
        });
    });
    document.querySelectorAll('[data-tasks-filter]').forEach(subBtn => {
        subBtn.addEventListener('click', function () {
            const k = this.getAttribute('data-tasks-filter');
            taskViewButtons.forEach(b => {
                b.classList.toggle('active', b.getAttribute('data-task-view') === k);
            });
        });
    });
    document.querySelector('[data-task-action="new"]')?.addEventListener('click', function () {
        const input = document.getElementById('global-task-title');
        if (input) {
            input.focus();
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    // Listas: contactos, catálogo y difusión
    const listViewButtons = document.querySelectorAll('[data-list-view]');
    listViewButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const subtabKey = this.getAttribute('data-list-view') || 'contactos';
            listViewButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const subBtn = document.querySelector(`[data-lists-subtab="${subtabKey}"]`);
            if (subBtn) subBtn.click();
        });
    });
    document.querySelectorAll('[data-lists-subtab]').forEach(subBtn => {
        subBtn.addEventListener('click', function () {
            const k = this.getAttribute('data-lists-subtab');
            listViewButtons.forEach(b => {
                b.classList.toggle('active', b.getAttribute('data-list-view') === k);
            });
        });
    });

    // Flujos bot: accesos de zoom, auto-layout y nuevo nodo
    document.querySelectorAll('[data-node-tool]').forEach(btn => {
        btn.addEventListener('click', function () {
            const tool = this.getAttribute('data-node-tool');
            if (tool === 'zoom-out') document.getElementById('btn-zoom-out')?.click();
            if (tool === 'zoom-in') document.getElementById('btn-zoom-in')?.click();
            if (tool === 'zoom-reset') document.getElementById('btn-zoom-reset')?.click();
            if (tool === 'auto-layout') document.getElementById('btn-auto-layout')?.click();
            if (tool === 'add-node') document.getElementById('btn-add-node-canvas')?.click();
        });
    });

    // Prompt IA: foco en el textarea y botón guardar
    document.querySelectorAll('[data-prompt-tool]').forEach(btn => {
        btn.addEventListener('click', function () {
            const tool = this.getAttribute('data-prompt-tool');
            if (tool === 'focus') {
                document.getElementById('live_system_prompt')?.focus();
            } else if (tool === 'save') {
                const saveBtn = document.getElementById('btnGuardarPrompt');
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.click();
                }
            }
        });
    });

    // Inicializar en la pestaña guardada o en el hash de la URL (por defecto Dashboard)
    const initialHash = window.location.hash ? window.location.hash.substring(1) : '';
    const storedTab = instanciaId ? sessionStorage.getItem('instancia_tab_' + instanciaId) : null;
    switchTab(initialHash || storedTab || 'dashboard');

    // Toast Feedback discreto bajo la barra flotante
    const feedback = document.getElementById('floating-feedback');
    let feedbackTimer;

    function showFeedback(text) {
        if (!feedback || !text) return;
        feedback.textContent = text;
        feedback.classList.add('visible');
        clearTimeout(feedbackTimer);
        feedbackTimer = setTimeout(() => {
            feedback.classList.remove('visible');
        }, 1300);
    }

    document.querySelectorAll('[data-action]').forEach(button => {
        button.addEventListener('click', () => {
            showFeedback(button.dataset.action);
        });
    });

    window.InstanceNavigation = {
        switchTab: switchTab,
        showFeedback: showFeedback
    };
});
