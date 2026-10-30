// CRM: embudo kanban, chat en vivo, cotizador y métricas de la instancia
document.addEventListener('DOMContentLoaded', function () {
    const configEl = document.getElementById('blueprint-config');
    const csrfToken = configEl?.getAttribute('data-csrf-token') ||
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const instanciaId = configEl?.getAttribute('data-instancia-id');

    if (!instanciaId) return;

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function formatCurrency(val) {
        const num = Number(val) || 0;
        return '$' + num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    let leads = [];
    let stages = {};
    let catalog = [];
    let templates = [];
    let automations = {};
    let broadcasts = [];
    let customFieldsDef = [];
    let advisors = [];

    let knowledgeBase = [];
    let fiscalData = {};
    let scheduleData = {};
    let webhookData = {};
    let planInfo = {};
    let apiToken = '';

    try {
        leads = JSON.parse(document.getElementById('crm-leads-json')?.textContent || '[]');
        stages = JSON.parse(document.getElementById('crm-stages-json')?.textContent || '{}');
        catalog = JSON.parse(document.getElementById('crm-catalog-json')?.textContent || '[]');
        templates = JSON.parse(document.getElementById('crm-templates-json')?.textContent || '[]');
        automations = JSON.parse(document.getElementById('crm-automations-json')?.textContent || '{}');
        broadcasts = JSON.parse(document.getElementById('crm-broadcasts-json')?.textContent || '[]');
        customFieldsDef = JSON.parse(document.getElementById('crm-custom-fields-json')?.textContent || '[]');
        advisors = JSON.parse(document.getElementById('crm-advisors-json')?.textContent || '[]');
        knowledgeBase = JSON.parse(document.getElementById('crm-base-conocimiento-json')?.textContent || '[]');
        fiscalData = JSON.parse(document.getElementById('crm-fiscal-json')?.textContent || '{}');
        scheduleData = JSON.parse(document.getElementById('crm-horario-json')?.textContent || '{}');
        webhookData = JSON.parse(document.getElementById('crm-webhook-json')?.textContent || '{}');
        planInfo = JSON.parse(document.getElementById('crm-plan-json')?.textContent || '{}');
        apiToken = document.getElementById('crm-api-token')?.textContent?.trim() || '';
    } catch (e) {
        console.error('Error parseando datos iniciales del CRM:', e);
    }

    let activeLeadId = leads.length > 0 ? leads[0].id : null;
    let inboxFilter = 'all';
    let inboxSearchQuery = '';
    let pipelineSearchQuery = '';
    let pipelineBotFilter = 'all';
    let pipelineSort = 'recent';
    let pipelineDensity = localStorage.getItem('crm_pipeline_density') || 'compact';
    let tasksFilter = 'pendientes';
    let directorySearchQuery = '';
    let lastMsgId = 0;
    let isInternalNoteMode = false;

    function getActiveLead() {
        return leads.find(l => Number(l.id) === Number(activeLeadId)) || null;
    }

    // Métricas y funnel del dashboard
    function renderDashboard() {
        const totalLeads = leads.length;
        const wonLeads = leads.filter(l => l.etapa === 'ganado');
        const totalPipelineVal = leads.reduce((acc, l) => acc + (Number(l.valor_estimado) || 0), 0);
        const wonVal = wonLeads.reduce((acc, l) => acc + (Number(l.valor_estimado) || 0), 0);
        const conversionRate = totalLeads > 0 ? Math.round((wonLeads.length / totalLeads) * 100) : 0;
        const botActiveCount = leads.filter(l => l.is_bot_active).length;
        const humanActiveCount = totalLeads - botActiveCount;

        let pendingTasksCount = 0;
        leads.forEach(l => {
            if (Array.isArray(l.tareas)) {
                pendingTasksCount += l.tareas.filter(t => !t.completada).length;
            }
        });

        const setElText = (id, txt) => {
            const el = document.getElementById(id);
            if (el) el.textContent = txt;
        };

        setElText('dash-total-leads', String(totalLeads));
        setElText('dash-conversion-rate', conversionRate + '%');
        setElText('dash-pipeline-value', formatCurrency(totalPipelineVal));
        setElText('dash-won-value', formatCurrency(wonVal));
        setElText('dash-pending-tasks', String(pendingTasksCount));
        setElText('dash-bot-ratio', `${botActiveCount} Bot / ${humanActiveCount} Humano`);

        const funnelContainer = document.getElementById('crm-dashboard-funnel');
        const funnelTotalBadge = document.getElementById('dash-funnel-total-badge');
        const segmentedBar = document.getElementById('dash-segmented-flow-bar');
        const avgTicketEl = document.getElementById('dash-avg-ticket');

        if (funnelTotalBadge) {
            funnelTotalBadge.textContent = formatCurrency(totalPipelineVal);
        }
        if (avgTicketEl) {
            const avgVal = totalLeads > 0 ? Math.round(totalPipelineVal / totalLeads) : 0;
            avgTicketEl.textContent = formatCurrency(avgVal);
        }

        // Barra de flujo segmentada de pipeline
        if (segmentedBar) {
            segmentedBar.innerHTML = '';
            if (totalLeads === 0) {
                segmentedBar.innerHTML = '<div style="width: 100%; height: 100%; background: #e2e8f0;"></div>';
            } else {
                Object.entries(stages).forEach(([key, meta]) => {
                    const count = leads.filter(l => (l.etapa || 'nuevo') === key).length;
                    if (count > 0) {
                        const pct = (count / totalLeads) * 100;
                        const seg = document.createElement('div');
                        seg.style.width = pct + '%';
                        seg.style.background = meta.color || '#3b82f6';
                        seg.style.height = '100%';
                        seg.title = `${meta.label}: ${count} (${Math.round(pct)}%)`;
                        segmentedBar.appendChild(seg);
                    }
                });
            }
        }

        if (funnelContainer) {
            funnelContainer.innerHTML = '';
            Object.entries(stages).forEach(([key, meta]) => {
                const stageLeads = leads.filter(l => (l.etapa || 'nuevo') === key);
                const pct = totalLeads > 0 ? Math.round((stageLeads.length / totalLeads) * 100) : 0;
                const sum = stageLeads.reduce((acc, l) => acc + (Number(l.valor_estimado) || 0), 0);

                const card = document.createElement('div');
                card.className = 'crm-funnel-card';
                card.setAttribute('title', 'Ver etapa ' + meta.label + ' en el embudo Kanban');
                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="d-flex align-items-center gap-2 text-truncate mr-2">
                            <span class="crm-stage-dot" style="background:${escapeHtml(meta.color)}"></span>
                            <span class="crm-stage-name text-truncate" style="font-size:0.86rem;">${escapeHtml(meta.label)}</span>
                        </div>
                        <span class="crm-stage-count-pill flex-shrink-0">${stageLeads.length} (${pct}%)</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="small text-muted" style="font-size:0.75rem;">Volumen acumulado</span>
                        <span class="crm-stage-amount font-mono" style="font-size:0.92rem;">${formatCurrency(sum)}</span>
                    </div>
                    <div class="crm-funnel-track mt-1" style="height:6px;">
                        <div class="crm-funnel-fill" style="width: ${Math.max(pct, stageLeads.length > 0 ? 8 : 0)}%; background: ${escapeHtml(meta.color)};"></div>
                    </div>
                `;
                card.addEventListener('click', function () {
                    const pipeNavBtn = document.querySelector('[data-nav="pipeline"]');
                    if (pipeNavBtn) pipeNavBtn.click();
                });
                funnelContainer.appendChild(card);
            });
        }

        renderDashboardActiveChats();
        renderDashboardLeadsTable();
    }

    function renderDashboardLeadsTable() {
        const tbody = document.getElementById('crm-dashboard-leads-table-body');
        const countBadge = document.getElementById('dash-leads-table-count');
        if (!tbody) return;

        if (countBadge) countBadge.textContent = `${leads.length} ${leads.length === 1 ? 'contacto' : 'contactos'}`;

        if (leads.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fab fa-whatsapp fa-2x mb-2 text-muted" style="opacity: 0.35;"></i>
                        <div>No hay leads registrados en esta instancia.</div>
                    </td>
                </tr>
            `;
            return;
        }

        const sortedLeads = [...leads].sort((a, b) => {
            const tA = a.ultimo_mensaje_cliente_iso ? new Date(a.ultimo_mensaje_cliente_iso).getTime() : 0;
            const tB = b.ultimo_mensaje_cliente_iso ? new Date(b.ultimo_mensaje_cliente_iso).getTime() : 0;
            return tB - tA;
        }).slice(0, 8);

        tbody.innerHTML = '';
        sortedLeads.forEach(lead => {
            const tr = document.createElement('tr');
            const initials = (lead.nombre || 'WA').slice(0, 2).toUpperCase();
            const stageMeta = stages[lead.etapa] || { label: lead.etapa || 'Nuevo', color: '#3b82f6' };
            const isBot = Boolean(lead.is_bot_active);

            let windowHtml = '';
            if (lead.ventana_24h_activa) {
                windowHtml = `<span class="badge badge-success px-2 py-1" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; font-size:0.75rem;"><i class="fas fa-clock mr-1"></i>Activa (${lead.ventana_24h_horas || 24}h)</span>`;
            } else {
                windowHtml = `<span class="badge badge-secondary px-2 py-1" style="background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; font-size:0.75rem;"><i class="fas fa-lock mr-1"></i>Expirada</span>`;
            }

            const botHtml = isBot
                ? `<span class="badge px-2 py-1" style="background:#eef2ff; color:#4f46e5; border:1px solid #c7d2fe; font-size:0.75rem;"><i class="fas fa-robot mr-1"></i>Bot IA</span>`
                : `<span class="badge px-2 py-1" style="background:#fffbeb; color:#d97706; border:1px solid #fde68a; font-size:0.75rem;"><i class="fas fa-user mr-1"></i>Asesor Humano</span>`;

            const lastMsgText = lead.ultimo_mensaje_body ? escapeHtml(lead.ultimo_mensaje_body) : '<span class="text-muted font-italic">Sin mensajes</span>';

            tr.innerHTML = `
                <td class="py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center font-weight-bold text-white shadow-sm flex-shrink-0" style="width:34px; height:34px; background:linear-gradient(135deg, #10b981, #059669); font-size:0.78rem;">
                            ${escapeHtml(initials)}
                        </div>
                        <div class="text-truncate" style="max-width: 180px;">
                            <div class="font-weight-bold text-dark text-truncate">${escapeHtml(lead.nombre || 'Cliente WhatsApp')}</div>
                            <div class="small text-muted font-mono">+${escapeHtml(lead.phone_number)}</div>
                        </div>
                    </div>
                </td>
                <td class="py-3">
                    <span class="badge px-2 py-1 font-weight-bold" style="background:${stageMeta.color}18; color:${stageMeta.color}; border:1px solid ${stageMeta.color}40; border-radius:6px; font-size:0.75rem;">
                        ${escapeHtml(stageMeta.label)}
                    </span>
                </td>
                <td class="py-3 font-mono font-weight-bold text-dark">
                    ${formatCurrency(Number(lead.valor_estimado) || 0)}
                </td>
                <td class="py-3">
                    ${botHtml}
                </td>
                <td class="py-3">
                    ${windowHtml}
                </td>
                <td class="py-3 text-muted text-truncate" style="max-width: 200px;" title="${escapeHtml(lead.ultimo_mensaje_body || '')}">
                    ${lastMsgText}
                </td>
                <td class="py-3 px-4 text-right">
                    <button type="button" class="btn btn-xs btn-outline-primary btn-dash-open-chat px-2 py-1" style="border-radius:6px;" data-lead-id="${lead.id}">
                        <i class="fas fa-comment-dots mr-1"></i> Abrir Chat
                    </button>
                </td>
            `;

            tr.querySelector('.btn-dash-open-chat').addEventListener('click', () => {
                openLeadInInbox(lead.id);
            });

            tbody.appendChild(tr);
        });
    }

    // Triage de conversaciones activas en el Dashboard
    let dashTriageFilter = 'all';
    function renderDashboardActiveChats() {
        const queueContainer = document.getElementById('crm-dashboard-active-chats');
        const badgeEl = document.getElementById('dash-active-chats-badge');
        if (!queueContainer) return;

        const activeChats = leads.filter(l => {
            const hasActivity = Boolean(l.ultimo_mensaje_body) || (Number(l.mensajes_sin_leer) || 0) > 0 || l.estado_conversacion !== 'resuelta';
            if (!hasActivity) return false;

            if (dashTriageFilter === 'human') {
                return !l.is_bot_active || l.etapa === 'asesor';
            }
            if (dashTriageFilter === 'unread') {
                return (Number(l.mensajes_sin_leer) || 0) > 0;
            }
            return true;
        }).sort((a, b) => {
            let pA = 0;
            let pB = 0;
            if ((Number(a.mensajes_sin_leer) || 0) > 0) pA += 10000000;
            if (!a.is_bot_active) pA += 5000000;
            pA += (Number(a.ultimo_mensaje_id) || 0);

            if ((Number(b.mensajes_sin_leer) || 0) > 0) pB += 10000000;
            if (!b.is_bot_active) pB += 5000000;
            pB += (Number(b.ultimo_mensaje_id) || 0);

            return pB - pA;
        });

        if (badgeEl) {
            badgeEl.textContent = `${activeChats.length}`;
        }

        if (activeChats.length === 0) {
            queueContainer.innerHTML = `
                <div class="text-center text-muted small py-5">
                    <i class="fab fa-whatsapp fa-2x mb-2 text-muted" style="opacity: 0.35;"></i>
                    <div>No hay conversaciones que coincidan con este filtro.</div>
                </div>
            `;
            return;
        }

        queueContainer.innerHTML = '';
        activeChats.forEach(c => {
            const row = document.createElement('div');
            row.className = 'crm-dash-chat-row';
            row.dataset.leadId = String(c.id);

            const initial = (c.nombre || 'C').trim().charAt(0).toUpperCase();
            const stageMeta = stages[c.etapa] || stages.nuevo || { label: 'Nuevo', color: '#3b82f6' };
            const isHumanWait = !c.is_bot_active;
            const hasUnread = (Number(c.mensajes_sin_leer) || 0) > 0;

            const snippet = c.ultimo_mensaje_body || 'Conversación abierta';
            const snippetHtml = c.ultimo_mensaje_direction === 'outbound'
                ? `<i class="fas fa-reply text-muted mr-1" style="font-size:0.7rem;"></i>${escapeHtml(snippet)}`
                : escapeHtml(snippet);

            row.innerHTML = `
                <div class="dash-chat-avatar-wrapper">
                    <div class="dash-chat-avatar">
                        ${escapeHtml(initial)}
                    </div>
                    <span class="dash-chat-status-dot ${isHumanWait ? 'dot-human' : 'dot-bot'}" title="${isHumanWait ? 'Atención Humana requerida' : 'Atendido por Bot'}"></span>
                </div>
                <div class="dash-chat-body">
                    <div class="dash-chat-header-row">
                        <div class="d-flex align-items-center gap-1 text-truncate">
                            <span class="dash-chat-name" title="${escapeHtml(c.nombre)}">${escapeHtml(c.nombre)}</span>
                            <span class="dash-chat-phone">+${escapeHtml(c.phone_number)}</span>
                        </div>
                        <span class="dash-chat-time">${escapeHtml(c.ultimo_mensaje_human || c.updated_human || 'Reciente')}</span>
                    </div>
                    <div class="dash-chat-preview-row">
                        <div class="dash-chat-snippet text-truncate">
                            ${snippetHtml}
                        </div>
                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                            ${hasUnread ? `<span class="crm-unread-badge">${Number(c.mensajes_sin_leer)}</span>` : ''}
                            <span class="crm-badge" style="background: ${escapeHtml(stageMeta.color)}15; color: ${escapeHtml(stageMeta.color)}; font-size: 0.68rem; padding: 2px 6px;">
                                ${escapeHtml(stageMeta.label)}
                            </span>
                        </div>
                    </div>
                </div>
            `;

            row.addEventListener('click', function () {
                openLeadInInbox(c.id);
            });

            queueContainer.appendChild(row);
        });
    }

    document.querySelectorAll('.dash-triage-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.dash-triage-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            dashTriageFilter = this.dataset.triageFilter || 'all';
            renderDashboardActiveChats();
        });
    });

    // Pipeline kanban y filtros de leads
    const kanbanBoard = document.getElementById('crm-kanban-board');
    const kpiTotalLeads = document.getElementById('kpi-total-leads');
    const kpiTotalValue = document.getElementById('kpi-total-value');
    const kpiHumanNeeded = document.getElementById('kpi-human-needed');
    const pipelineFilteredCount = document.getElementById('pipelineFilteredCount');
    const inputPipelineSearch = document.getElementById('inputPipelineSearch');
    const selectPipelineBotFilter = document.getElementById('selectPipelineBotFilter');
    const selectPipelineSort = document.getElementById('selectPipelineSort');
    const pipelineDensityToggle = document.getElementById('pipelineDensityToggle');

    function getFilteredPipelineLeads() {
        let filtered = leads.filter(lead => {
            if (pipelineBotFilter === 'bot' && !lead.is_bot_active) return false;
            if (pipelineBotFilter === 'human' && lead.is_bot_active) return false;
            if (pipelineSearchQuery) {
                const q = pipelineSearchQuery.toLowerCase();
                const matchName = (lead.nombre || '').toLowerCase().includes(q);
                const matchCompany = (lead.empresa_cliente || '').toLowerCase().includes(q);
                const matchPhone = (lead.phone_number || '').toLowerCase().includes(q);
                const matchTag = Array.isArray(lead.etiquetas) && lead.etiquetas.some(t => t.toLowerCase().includes(q));
                if (!matchName && !matchCompany && !matchPhone && !matchTag) return false;
            }
            return true;
        });

        // Ordenamiento inteligente
        if (pipelineSort === 'value_desc') {
            filtered.sort((a, b) => (Number(b.valor_estimado) || 0) - (Number(a.valor_estimado) || 0));
        } else if (pipelineSort === 'unread') {
            filtered.sort((a, b) => {
                const unreadA = Number(a.mensajes_sin_leer) || 0;
                const unreadB = Number(b.mensajes_sin_leer) || 0;
                if (unreadB !== unreadA) return unreadB - unreadA;
                return (b.id || 0) - (a.id || 0);
            });
        } else if (pipelineSort === 'name_asc') {
            filtered.sort((a, b) => (a.nombre || '').localeCompare(b.nombre || '', undefined, { sensitivity: 'base' }));
        } else {
            // 'recent': sort by updated_at or id descending
            filtered.sort((a, b) => {
                const timeA = a.updated_at ? new Date(a.updated_at).getTime() : (a.id || 0);
                const timeB = b.updated_at ? new Date(b.updated_at).getTime() : (b.id || 0);
                return timeB - timeA;
            });
        }

        return filtered;
    }

    function renderKanbanBoard() {
        if (!kanbanBoard) return;

        const filtered = getFilteredPipelineLeads();
        const totalValue = leads.reduce((acc, l) => acc + (Number(l.valor_estimado) || 0), 0);
        const humanCount = leads.filter(l => l.etapa === 'asesor' || !l.is_bot_active).length;

        if (kpiTotalLeads) kpiTotalLeads.textContent = String(leads.length);
        if (kpiTotalValue) kpiTotalValue.textContent = formatCurrency(totalValue);
        if (kpiHumanNeeded) kpiHumanNeeded.textContent = String(humanCount);

        if (pipelineFilteredCount) {
            if (pipelineSearchQuery || pipelineBotFilter !== 'all') {
                pipelineFilteredCount.textContent = `Mostrando ${filtered.length} de ${leads.length} leads`;
                pipelineFilteredCount.classList.remove('d-none');
            } else {
                pipelineFilteredCount.classList.add('d-none');
            }
        }

        kanbanBoard.innerHTML = '';

        Object.entries(stages).forEach(([stageKey, stageMeta]) => {
            const stageLeads = filtered.filter(l => (l.etapa || 'nuevo') === stageKey);
            const stageSum = stageLeads.reduce((acc, l) => acc + (Number(l.valor_estimado) || 0), 0);
            const rule = automations[stageKey];
            const hasRule = rule && (rule.mensaje_whatsapp || rule.auto_tag || rule.crear_tarea || (rule.accion_bot && rule.accion_bot !== 'ninguna'));

            const col = document.createElement('div');
            col.className = 'crm-kanban-column';
            col.dataset.stage = stageKey;
            col.style.setProperty('--stage-color', stageMeta.color || '#3b82f6');

            col.innerHTML = `
                <div class="crm-column-header">
                    <div class="crm-column-title-row">
                        <h4 class="crm-column-title">
                            <i class="${escapeHtml(stageMeta.icon)}" style="color: ${escapeHtml(stageMeta.color)};"></i>
                            <span>${escapeHtml(stageMeta.label)}</span>
                            ${hasRule ? `<i class="fas fa-bolt text-warning ml-1" title="Etapa con disparador automático activo"></i>` : ''}
                        </h4>
                        <span class="crm-column-count">${stageLeads.length}</span>
                    </div>
                    <div class="crm-column-sub">
                        <span>Valor etapa:</span>
                        <strong>${formatCurrency(stageSum)}</strong>
                    </div>
                </div>
                <div class="crm-column-cards" data-stage-drop="${escapeHtml(stageKey)}"></div>
            `;

            const cardsContainer = col.querySelector('.crm-column-cards');

            if (stageLeads.length === 0) {
                cardsContainer.innerHTML = `
                    <div class="text-center text-muted small py-4" style="opacity: 0.65;">
                        Arrastra un lead aquí
                    </div>
                `;
            } else {
                stageLeads.forEach(lead => {
                    const card = document.createElement('div');
                    card.draggable = true;
                    card.dataset.leadId = String(lead.id);

                    const unreadCount = Number(lead.mensajes_sin_leer) || 0;
                    const pendingTasks = Array.isArray(lead.tareas) ? lead.tareas.filter(t => !t.completada).length : 0;

                    if (pipelineDensity === 'compact') {
                        // Tarjeta de alta densidad (~52px)
                        card.className = 'crm-lead-card is-compact';

                        const statusDot = lead.is_bot_active
                            ? `<span class="crm-lead-status-dot is-bot" title="Atendido por Bot automático"></span>`
                            : `<span class="crm-lead-status-dot is-human" title="Atención humana requerida"></span>`;

                        const unreadBadge = unreadCount > 0
                            ? `<span class="crm-unread-badge-mini" title="${unreadCount} mensaje(s) sin leer">${unreadCount}</span>`
                            : '';

                        const taskIndicator = pendingTasks > 0
                            ? `<span class="crm-compact-indicator text-warning ml-1" title="${pendingTasks} tarea(s) pendiente(s)"><i class="fas fa-clock"></i> ${pendingTasks}</span>`
                            : '';

                        const lossIndicator = (lead.etapa === 'perdido' && lead.motivo_perdida)
                            ? `<span class="crm-compact-indicator text-danger ml-1" title="Motivo de pérdida: ${escapeHtml(lead.motivo_perdida)}"><i class="fas fa-times-circle"></i></span>`
                            : '';

                        const tagIndicator = (Array.isArray(lead.etiquetas) && lead.etiquetas.length > 0)
                            ? `<span class="crm-compact-indicator text-primary ml-1" title="Etiquetas: ${escapeHtml(lead.etiquetas.join(', '))}"><i class="fas fa-tag"></i></span>`
                            : '';

                        card.innerHTML = `
                            <div class="crm-compact-header">
                                <div class="crm-compact-lead-info">
                                    ${statusDot}
                                    <span class="crm-compact-name" title="${escapeHtml(lead.nombre)}">${escapeHtml(lead.nombre)}</span>
                                    ${unreadBadge}
                                </div>
                                <div class="crm-compact-val">${formatCurrency(lead.valor_estimado)}</div>
                            </div>
                            <div class="crm-compact-sub">
                                <div class="crm-compact-phone">
                                    <i class="fab fa-whatsapp text-success"></i> +${escapeHtml(lead.phone_number)}
                                    ${taskIndicator}
                                    ${lossIndicator}
                                    ${tagIndicator}
                                </div>
                                <div class="crm-compact-time">${escapeHtml(lead.updated_human || '')}</div>
                            </div>
                        `;
                    } else {
                        // Tarjeta expandida / detallada
                        card.className = 'crm-lead-card is-detailed';

                        const tagsHtml = (lead.etiquetas || []).slice(0, 3).map(t =>
                            `<span class="crm-badge crm-badge-tag">${escapeHtml(t)}</span>`
                        ).join('');

                        const taskBadge = pendingTasks > 0
                            ? `<span class="crm-badge crm-badge-task"><i class="fas fa-clock"></i> ${pendingTasks} tarea(s)</span>`
                            : '';

                        const botBadge = lead.is_bot_active
                            ? `<span class="crm-badge crm-badge-bot"><i class="fas fa-robot"></i> Bot</span>`
                            : `<span class="crm-badge crm-badge-human"><i class="fas fa-user-check"></i> Humano</span>`;

                        const unreadBadge = unreadCount > 0
                            ? `<span class="crm-unread-badge" title="Mensajes sin leer">${unreadCount}</span>`
                            : '';

                        const advisorBadge = lead.asesor_nombre
                            ? `<span class="crm-badge" style="background:#f1f5f9; color:#334155;"><i class="fas fa-user-tie"></i> ${escapeHtml(lead.asesor_nombre)}</span>`
                            : '';

                        const lossNoteHtml = (lead.etapa === 'perdido' && lead.motivo_perdida)
                            ? `<div class="small text-danger mb-2" style="font-size:0.72rem;"><i class="fas fa-times-circle mr-1"></i>Motivo: ${escapeHtml(lead.motivo_perdida)}</div>`
                            : '';

                        card.innerHTML = `
                            <div class="crm-card-top">
                                <div class="crm-card-name">${escapeHtml(lead.nombre)}</div>
                                <div class="d-flex align-items-center gap-1">
                                    ${unreadBadge}
                                    <span class="text-muted" style="font-size: 0.68rem;">${escapeHtml(lead.updated_human || '')}</span>
                                </div>
                            </div>
                            ${lead.empresa_cliente ? `<div class="crm-card-company"><i class="far fa-building mr-1"></i>${escapeHtml(lead.empresa_cliente)}</div>` : ''}
                            <div class="crm-card-phone">
                                <i class="fab fa-whatsapp"></i> +${escapeHtml(lead.phone_number)}
                            </div>
                            ${lossNoteHtml}
                            <div class="crm-card-badges">
                                ${botBadge}
                                ${advisorBadge}
                                ${taskBadge}
                                <span class="crm-badge crm-badge-node">#${escapeHtml(lead.current_node || 'main')}</span>
                                ${tagsHtml}
                            </div>
                            <div class="crm-card-footer">
                                <span class="crm-card-value">${formatCurrency(lead.valor_estimado)}</span>
                                <button type="button" class="btn-saas btn-saas-secondary btn-sm py-1 px-2 btn-open-in-inbox" data-lead-id="${lead.id}">
                                    <i class="fas fa-comments text-primary mr-1"></i> Abrir Chat
                                </button>
                            </div>
                        `;
                    }

                    card.addEventListener('dragstart', function (e) {
                        card.classList.add('is-dragging');
                        e.dataTransfer.setData('text/plain', String(lead.id));
                        e.dataTransfer.effectAllowed = 'move';
                    });

                    card.addEventListener('dragend', function () {
                        card.classList.remove('is-dragging');
                        document.querySelectorAll('.crm-kanban-column').forEach(c => c.classList.remove('is-drag-over'));
                    });

                    card.querySelector('.btn-open-in-inbox')?.addEventListener('click', function (e) {
                        e.stopPropagation();
                        openLeadInInbox(lead.id);
                    });

                    card.addEventListener('click', function () {
                        openLeadInInbox(lead.id);
                    });

                    cardsContainer.appendChild(card);
                });
            }

            col.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                col.classList.add('is-drag-over');
            });

            col.addEventListener('dragleave', function (e) {
                if (!col.contains(e.relatedTarget)) {
                    col.classList.remove('is-drag-over');
                }
            });

            col.addEventListener('drop', function (e) {
                e.preventDefault();
                col.classList.remove('is-drag-over');
                const draggedId = Number(e.dataTransfer.getData('text/plain'));
                if (!draggedId) return;

                const targetLead = leads.find(l => Number(l.id) === draggedId);
                if (!targetLead || targetLead.etapa === stageKey) return;

                const payload = { etapa: stageKey };
                if (stageKey === 'perdido') {
                    const reason = window.prompt('Indica el motivo de pérdida de esta oportunidad (obligatorio):', targetLead.motivo_perdida || '');
                    if (reason === null || !reason.trim()) {
                        return;
                    }
                    targetLead.motivo_perdida = reason.trim();
                    payload.motivo_perdida = targetLead.motivo_perdida;
                }

                targetLead.etapa = stageKey;
                if (stageKey === 'asesor') targetLead.is_bot_active = false;
                refreshAll();
                updateLeadCrmAjax(draggedId, payload);
            });

            kanbanBoard.appendChild(col);
        });
    }

    inputPipelineSearch?.addEventListener('input', function () {
        pipelineSearchQuery = this.value.trim();
        renderKanbanBoard();
    });

    selectPipelineBotFilter?.addEventListener('change', function () {
        pipelineBotFilter = this.value;
        renderKanbanBoard();
    });

    selectPipelineSort?.addEventListener('change', function () {
        pipelineSort = this.value;
        renderKanbanBoard();
    });

    if (pipelineDensityToggle) {
        const densityButtons = pipelineDensityToggle.querySelectorAll('.crm-density-btn');
        densityButtons.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.density === pipelineDensity);
            btn.addEventListener('click', function () {
                densityButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                pipelineDensity = this.dataset.density;
                localStorage.setItem('crm_pipeline_density', pipelineDensity);
                renderKanbanBoard();
            });
        });
    }

    function openLeadInInbox(leadId) {
        activeLeadId = Number(leadId);
        if (window.InstanceNavigation && typeof window.InstanceNavigation.switchTab === 'function') {
            window.InstanceNavigation.switchTab('chats');
        }
        selectInboxLead(activeLeadId);
    }

    // Alta manual de prospectos desde el modal
    document.getElementById('formCrmCreateLead')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const form = this;
        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;

        const payload = {
            nombre: form.nombre.value.trim(),
            phone_number: form.phone_number.value.trim(),
            empresa_cliente: form.empresa_cliente?.value.trim() || '',
            email: form.email?.value.trim() || '',
            etapa: form.etapa.value || 'nuevo',
            valor_estimado: Number(form.valor_estimado.value) || 0,
            etiquetas: form.etiquetas.value.trim()
        };

        fetch(`/instancias/${instanciaId}/contactos`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.lead) {
                    const existingIdx = leads.findIndex(l => Number(l.id) === Number(data.lead.id));
                    if (existingIdx >= 0) {
                        leads[existingIdx] = data.lead;
                    } else {
                        leads.unshift(data.lead);
                    }
                    activeLeadId = data.lead.id;
                    form.reset();
                    if (window.$) window.$('#modalCrmNuevoLead').modal('hide');
                    refreshAll();
                } else {
                    alert(data.error || 'Error al registrar el lead.');
                }
            })
            .finally(() => {
                if (btn) btn.disabled = false;
            });
    });

    // Modal de edición de etapas del embudo
    const stagesEditorList = document.getElementById('crm-stages-editor-list');
    function renderStagesEditor() {
        if (!stagesEditorList) return;
        stagesEditorList.innerHTML = '';
        Object.entries(stages).forEach(([key, meta]) => {
            const isFinal = (key === 'ganado' || key === 'perdido');
            const row = document.createElement('div');
            row.className = 'd-flex align-items-center gap-2 p-2 stage-editor-row';
            row.style.cssText = 'background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;';
            row.dataset.stageKey = key;
            row.innerHTML = `
                <input type="color" class="stage-input-color" value="${escapeHtml(meta.color || '#3b82f6')}" style="width:36px; height:32px; border:none; background:transparent; cursor:pointer;">
                <input type="text" class="saas-input py-1 px-2 stage-input-label flex-grow-1" value="${escapeHtml(meta.label || key)}" required>
                <input type="text" class="saas-input py-1 px-2 font-mono stage-input-icon" style="width:150px;" value="${escapeHtml(meta.icon || 'fas fa-circle')}" placeholder="fas fa-circle">
                <span class="badge badge-light font-mono">${escapeHtml(key)}</span>
                ${isFinal
                    ? `<span class="badge badge-secondary" title="Etapa final obligatoria">Fija</span>`
                    : `<button type="button" class="btn btn-xs btn-outline-danger btn-remove-stage-row" title="Eliminar etapa"><i class="fas fa-trash-alt"></i></button>`
                }
            `;
            row.querySelector('.btn-remove-stage-row')?.addEventListener('click', () => row.remove());
            stagesEditorList.appendChild(row);
        });
    }
    renderStagesEditor();

    document.getElementById('btnAddStageRow')?.addEventListener('click', function () {
        if (!stagesEditorList) return;
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center gap-2 p-2 stage-editor-row';
        row.style.cssText = 'background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;';
        row.dataset.stageKey = '';
        row.innerHTML = `
            <input type="color" class="stage-input-color" value="#6366f1" style="width:36px; height:32px; border:none; background:transparent; cursor:pointer;">
            <input type="text" class="saas-input py-1 px-2 stage-input-label flex-grow-1" placeholder="Nombre de la nueva etapa" required>
            <input type="text" class="saas-input py-1 px-2 font-mono stage-input-icon" style="width:150px;" value="fas fa-flag" placeholder="fas fa-flag">
            <span class="badge badge-info">Nueva</span>
            <button type="button" class="btn btn-xs btn-outline-danger btn-remove-stage-row"><i class="fas fa-trash-alt"></i></button>
        `;
        row.querySelector('.btn-remove-stage-row')?.addEventListener('click', () => row.remove());
        stagesEditorList.appendChild(row);
    });

    document.getElementById('btnResetDefaultStages')?.addEventListener('click', function () {
        fetch(`/instancias/${instanciaId}/etapas`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ restaurar_default: true })
        }).then(r => r.json()).then(d => {
            if (d.success && d.etapas) {
                stages = d.etapas;
                renderStagesEditor();
                syncStageSelectOptions();
                refreshAll();
                if (window.$) window.$('#modalCrmEtapas').modal('hide');
            }
        });
    });

    document.getElementById('formCrmStagesConfig')?.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!stagesEditorList) return;
        const payloadStages = [];
        stagesEditorList.querySelectorAll('.stage-editor-row').forEach(row => {
            const label = row.querySelector('.stage-input-label')?.value.trim();
            if (!label) return;
            payloadStages.push({
                key: row.dataset.stageKey || label,
                label: label,
                color: row.querySelector('.stage-input-color')?.value || '#3b82f6',
                icon: row.querySelector('.stage-input-icon')?.value.trim() || 'fas fa-circle'
            });
        });

        fetch(`/instancias/${instanciaId}/etapas`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ etapas: payloadStages })
        }).then(r => r.json()).then(d => {
            if (d.success && d.etapas) {
                stages = d.etapas;
                renderStagesEditor();
                syncStageSelectOptions();
                refreshAll();
                if (window.$) window.$('#modalCrmEtapas').modal('hide');
            }
        });
    });

    function syncStageSelectOptions() {
        const selectEl = document.getElementById('crm-select-etapa');
        if (selectEl) {
            const currentVal = selectEl.value;
            selectEl.innerHTML = '';
            Object.entries(stages).forEach(([k, m]) => {
                selectEl.innerHTML += `<option value="${escapeHtml(k)}">${escapeHtml(m.label || k)}</option>`;
            });
            if (stages[currentVal]) selectEl.value = currentVal;
        }
    }

    // Automatizaciones al cambiar de etapa (mensajes, tags, tareas)
    document.getElementById('formCrmStageAutomations')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;

        const rules = {};
        Object.keys(stages).forEach(stageKey => {
            rules[stageKey] = {
                mensaje_whatsapp: document.getElementById(`auto_msg_${stageKey}`)?.value.trim() || '',
                auto_tag: document.getElementById(`auto_tag_${stageKey}`)?.value.trim() || '',
                crear_tarea: document.getElementById(`auto_task_${stageKey}`)?.value.trim() || '',
                accion_bot: document.getElementById(`auto_bot_${stageKey}`)?.value || 'ninguna'
            };
        });

        fetch(`/instancias/${instanciaId}/automatizaciones`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ automatizaciones: rules })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    automations = data.automatizaciones || rules;
                    if (window.$) window.$('#modalCrmAutomatizaciones').modal('hide');
                    renderKanbanBoard();
                }
            })
            .finally(() => {
                if (btn) btn.disabled = false;
            });
    });

    // Bandeja de entrada, chat y ficha del lead
    const inboxListEl = document.getElementById('crm-inbox-list');
    const inputInboxSearch = document.getElementById('inputInboxSearch');
    const filterPills = document.querySelectorAll('.crm-filter-pill');
    const messagesViewport = document.getElementById('crm-messages-viewport');
    const topbarName = document.getElementById('inbox-active-name');
    const topbarPhone = document.getElementById('inbox-active-phone');
    const topbarNode = document.getElementById('inbox-active-node');
    const topbarWindow24h = document.getElementById('inbox-active-window24h');
    const selectEstadoConv = document.getElementById('inbox-select-estado-conv');
    const selectAsesor = document.getElementById('inbox-select-asesor');
    const btnToggleBot = document.getElementById('btnInboxToggleBot');
    const btnIaResumen = document.getElementById('btnInboxIaResumen');
    const btnSidebarGenerateSummary = document.getElementById('btnSidebarGenerateSummary');
    const sidebarSummaryBox = document.getElementById('crm-sidebar-summary-box');

    const formInboxSend = document.getElementById('formCrmInboxSend');
    const inputInboxMessage = document.getElementById('inputCrmInboxMessage');
    const btnInboxSend = document.getElementById('btnCrmInboxSend');
    const btnToggleInternalNoteMode = document.getElementById('btnToggleInternalNoteMode');
    const btnInboxAttachMedia = document.getElementById('btnInboxAttachMedia');
    const inputInboxMediaFile = document.getElementById('inputInboxMediaFile');
    const crmComposerMediaPreview = document.getElementById('crmComposerMediaPreview');
    const crmComposerThumb = document.getElementById('crmComposerThumb');
    const crmComposerFileName = document.getElementById('crmComposerFileName');
    const crmComposerFileSize = document.getElementById('crmComposerFileSize');
    const btnRemoveComposerMedia = document.getElementById('btnRemoveComposerMedia');
    let attachedMediaFile = null;
    const composerContainer = document.getElementById('crmComposerContainer');
    const templatesPopover = document.getElementById('crmTemplatesPopover');
    const btnToggleTemplatesPopover = document.getElementById('btnToggleTemplatesPopover');

    // Ficha lateral del lead (columna derecha)
    const crmInputNombre = document.getElementById('crm-input-nombre');
    const crmInputEmpresa = document.getElementById('crm-input-empresa');
    const crmInputEmail = document.getElementById('crm-input-email');
    const crmSelectEtapa = document.getElementById('crm-select-etapa');
    const crmInputValor = document.getElementById('crm-input-valor');
    const crmLossReasonBox = document.getElementById('crm-loss-reason-box');
    const crmInputMotivoPerdida = document.getElementById('crm-input-motivo-perdida');
    const crmCheckboxOptIn = document.getElementById('crm-checkbox-opt-in');
    const crmCustomFieldsContainer = document.getElementById('crm-custom-fields-container');
    const crmTagsContainer = document.getElementById('crm-tags-container');
    const crmInputNewTag = document.getElementById('crm-input-new-tag');
    const crmBtnAddTag = document.getElementById('crm-btn-add-tag');
    const crmTasksContainer = document.getElementById('crm-tasks-container');
    const crmInputTaskTitle = document.getElementById('crm-input-task-title');
    const crmSelectTaskType = document.getElementById('crm-select-task-type');
    const crmBtnAddTask = document.getElementById('crm-btn-add-task');
    const crmNotesContainer = document.getElementById('crm-notes-container');
    const crmInputNoteText = document.getElementById('crm-input-note-text');
    const crmBtnAddNote = document.getElementById('crm-btn-add-note');

    // Tabs de la ficha: datos generales vs cotizador vs auditoría/timeline
    document.querySelectorAll('.crm-inspector-tab').forEach(tabBtn => {
        tabBtn.addEventListener('click', function () {
            document.querySelectorAll('.crm-inspector-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const target = this.dataset.inspectorTab;
            document.getElementById('inspector-pane-main')?.classList.toggle('d-none', target !== 'main');
            document.getElementById('inspector-pane-products')?.classList.toggle('d-none', target !== 'products');
            document.getElementById('inspector-pane-timeline')?.classList.toggle('d-none', target !== 'timeline');
        });
    });

    function renderInboxList() {
        if (!inboxListEl) return;

        const filtered = leads.filter(l => {
            if (inboxFilter === 'sin_leer' && (Number(l.mensajes_sin_leer) || 0) <= 0) return false;
            if (inboxFilter === 'resuelta' && l.estado_conversacion !== 'resuelta') return false;
            if (inboxFilter === 'asesor' && l.etapa !== 'asesor' && l.is_bot_active) return false;
            if (inboxFilter === 'bot' && !l.is_bot_active) return false;
            if (inboxFilter === 'cotizacion' && l.etapa !== 'cotizacion') return false;
            if (inboxFilter === 'tareas') {
                const hasPending = Array.isArray(l.tareas) && l.tareas.some(t => !t.completada);
                if (!hasPending) return false;
            }
            if (inboxSearchQuery) {
                const q = inboxSearchQuery.toLowerCase();
                const matchName = (l.nombre || '').toLowerCase().includes(q);
                const matchPhone = (l.phone_number || '').toLowerCase().includes(q);
                const matchCompany = (l.empresa_cliente || '').toLowerCase().includes(q);
                if (!matchName && !matchPhone && !matchCompany) return false;
            }
            return true;
        });

        if (filtered.length === 0) {
            inboxListEl.innerHTML = `<div class="text-center text-muted small py-5 px-3">No hay conversaciones que coincidan con el filtro.</div>`;
            return;
        }

        inboxListEl.innerHTML = '';
        filtered.forEach(lead => {
            const stageMeta = stages[lead.etapa] || stages.nuevo || { label: 'Nuevo', color: '#3b82f6' };
            const isSelected = Number(lead.id) === Number(activeLeadId);
            const item = document.createElement('div');
            item.className = `crm-chat-item ${isSelected ? 'active' : ''}`;
            item.dataset.leadId = String(lead.id);

            const initial = (lead.nombre || 'C').trim().charAt(0).toUpperCase();
            const isHumanWait = !lead.is_bot_active;
            const unreadCount = Number(lead.mensajes_sin_leer) || 0;
            const unreadHtml = unreadCount > 0 ? `<span class="crm-unread-badge">${unreadCount}</span>` : '';

            const snippet = lead.ultimo_mensaje_body || (lead.phone_number ? `+${lead.phone_number}` : 'Conversación abierta');
            const snippetHtml = lead.ultimo_mensaje_direction === 'outbound'
                ? `<i class="fas fa-reply text-muted mr-1" style="font-size:0.7rem;"></i>${escapeHtml(snippet)}`
                : escapeHtml(snippet);

            item.innerHTML = `
                <div class="dash-chat-avatar-wrapper">
                    <div class="crm-chat-avatar">${escapeHtml(initial)}</div>
                    <span class="dash-chat-status-dot ${isHumanWait ? 'dot-human' : 'dot-bot'}" title="${isHumanWait ? 'Atención Humana' : 'Bot Activo'}"></span>
                </div>
                <div class="crm-chat-meta">
                    <div class="crm-chat-name-row">
                        <span class="crm-chat-name text-truncate" title="${escapeHtml(lead.nombre)}">${escapeHtml(lead.nombre)}</span>
                        <span class="crm-chat-time">${escapeHtml(lead.ultimo_mensaje_human || lead.updated_human || '')}</span>
                    </div>
                    <div class="crm-chat-preview-row">
                        <span class="crm-chat-snippet text-truncate">${snippetHtml}</span>
                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                            ${unreadHtml}
                            <span class="crm-badge" style="background: ${escapeHtml(stageMeta.color)}18; color: ${escapeHtml(stageMeta.color)}; font-size: 0.68rem; padding: 1px 6px;">
                                ${escapeHtml(stageMeta.label)}
                            </span>
                        </div>
                    </div>
                </div>
            `;

            item.addEventListener('click', function () {
                selectInboxLead(lead.id);
            });

            inboxListEl.appendChild(item);
        });
    }

    inputInboxSearch?.addEventListener('input', function () {
        inboxSearchQuery = this.value.trim();
        renderInboxList();
    });

    filterPills.forEach(pill => {
        pill.addEventListener('click', function () {
            filterPills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            inboxFilter = this.dataset.filter || 'all';
            renderInboxList();
        });
    });

    function selectInboxLead(leadId) {
        activeLeadId = Number(leadId);
        lastMsgId = 0;
        resetComposerMedia();
        const lead = getActiveLead();
        if (lead) lead.mensajes_sin_leer = 0;
        renderInboxList();
        renderKanbanBoard();
        renderActiveLeadInspector();
        loadConversationMessages(false);
    }

    function formatSummaryContent(rawText) {
        if (!rawText) return '';
        const cleanText = rawText.replace(/[\u{1F600}-\u{1F64F}\u{1F300}-\u{1F5FF}\u{1F680}-\u{1F6FF}\u{1F700}-\u{1F77F}\u{1F780}-\u{1F7FF}\u{1F800}-\u{1F8FF}\u{1F900}-\u{1F9FF}\u{1FA00}-\u{1FA6F}\u{1FA70}-\u{1FAFF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F1E6}-\u{1F1FF}\u{FE00}-\u{FE0F}\u{200D}]/gu, '');
        let escaped = escapeHtml(cleanText.trim());
        return escaped
            .replace(/(•\s*[^:\n]+:)/g, '<strong class="text-dark d-block mt-2 mb-1" style="font-size: 0.82rem;">$1</strong>')
            .replace(/\n/g, '<br>');
    }

    function renderActiveLeadInspector() {
        const lead = getActiveLead();
        if (!lead) {
            if (topbarName) topbarName.textContent = 'Selecciona una conversación';
            if (topbarPhone) topbarPhone.textContent = '';
            return;
        }

        if (topbarName) topbarName.textContent = lead.nombre + (lead.empresa_cliente ? ` (${lead.empresa_cliente})` : '');
        if (topbarPhone) topbarPhone.textContent = '+' + lead.phone_number;
        if (topbarNode) topbarNode.textContent = '#' + (lead.current_node || 'main');

        if (topbarWindow24h) {
            topbarWindow24h.classList.remove('d-none', 'badge-success', 'badge-danger');
            if (lead.ventana_24h_activa) {
                topbarWindow24h.classList.add('badge-success');
                //topbarWindow24h.innerHTML = `<i class="far fa-clock mr-1"></i>Ventana 24h activa (${lead.ventana_24h_horas ?? 24}h)`;
            } else {
                topbarWindow24h.classList.add('badge-danger');
                //  topbarWindow24h.innerHTML = `<i class="fas fa-lock mr-1"></i>Ventana 24h cerrada (Requiere Plantilla Meta)`;
            }
        }

        if (selectEstadoConv) selectEstadoConv.value = lead.estado_conversacion || 'abierta';
        if (selectAsesor) selectAsesor.value = lead.asignado_a ? String(lead.asignado_a) : '';

        if (btnToggleBot) {
            btnToggleBot.className = lead.is_bot_active
                ? 'btn-saas btn-saas-secondary btn-sm text-primary'
                : 'btn-saas btn-saas-secondary btn-sm text-danger';
            btnToggleBot.innerHTML = lead.is_bot_active
                ? '<i class="fas fa-robot mr-1"></i>Pausar bot'
                : '<i class="fas fa-user-check mr-1"></i>Activar Bot';
        }

        if (sidebarSummaryBox) {
            if (lead.resumen_ia) {
                sidebarSummaryBox.innerHTML = formatSummaryContent(lead.resumen_ia);
            } else {
                sidebarSummaryBox.innerHTML = '<span class="text-muted font-italic">Sin resumen generado aún. Haz clic en "Resumen" para sintetizar la conversación.</span>';
            }
        }

        if (crmInputNombre) crmInputNombre.value = lead.nombre || '';
        if (crmInputEmpresa) crmInputEmpresa.value = lead.empresa_cliente || '';
        if (crmInputEmail) crmInputEmail.value = lead.email || '';
        if (crmSelectEtapa) crmSelectEtapa.value = lead.etapa || 'nuevo';
        if (crmInputValor) crmInputValor.value = lead.valor_estimado || 0;

        if (crmLossReasonBox) {
            crmLossReasonBox.classList.toggle('d-none', lead.etapa !== 'perdido');
        }
        if (crmInputMotivoPerdida) {
            crmInputMotivoPerdida.value = lead.motivo_perdida || '';
        }
        if (crmCheckboxOptIn) {
            crmCheckboxOptIn.checked = lead.opt_in !== false;
        }

        renderLeadCustomFields(lead);
        renderLeadTags(lead);
        renderLeadTasks(lead);
        renderLeadNotes(lead);
        renderLeadQuotedProducts(lead);
        renderLeadQuotesHistory(lead);
        renderLeadTimeline(lead);
    }

    function renderLeadCustomFields(lead) {
        if (!crmCustomFieldsContainer) return;
        if (!Array.isArray(customFieldsDef) || customFieldsDef.length === 0) {
            crmCustomFieldsContainer.innerHTML = `<div class="text-muted small">Sin campos personalizados. Créalos en <strong>Listas &rarr; Campos Personalizados</strong>.</div>`;
            return;
        }

        const values = (lead.campos_personalizados && typeof lead.campos_personalizados === 'object')
            ? lead.campos_personalizados
            : {};

        crmCustomFieldsContainer.innerHTML = '';
        customFieldsDef.forEach(def => {
            const val = values[def.key] ?? '';
            const wrap = document.createElement('div');
            wrap.className = 'form-group mb-2';

            let inputHtml = '';
            if (def.tipo === 'lista') {
                const opts = Array.isArray(def.opciones) ? def.opciones : [];
                inputHtml = `<select class="saas-input py-1 px-2 custom-field-input" data-field-key="${escapeHtml(def.key)}" style="height:30px; font-size:0.8rem;">
                    <option value="">-- Seleccionar --</option>
                    ${opts.map(o => `<option value="${escapeHtml(o)}" ${String(val) === String(o) ? 'selected' : ''}>${escapeHtml(o)}</option>`).join('')}
                </select>`;
            } else if (def.tipo === 'casilla') {
                inputHtml = `<label class="small text-dark d-flex align-items-center gap-1 mb-0" style="cursor:pointer;">
                    <input type="checkbox" class="custom-field-input" data-field-key="${escapeHtml(def.key)}" ${val ? 'checked' : ''}>
                    <span>${escapeHtml(def.nombre)}</span>
                </label>`;
            } else {
                const inputType = def.tipo === 'numero' ? 'number' : (def.tipo === 'fecha' ? 'date' : 'text');
                inputHtml = `<input type="${inputType}" class="saas-input py-1 px-2 custom-field-input" data-field-key="${escapeHtml(def.key)}" value="${escapeHtml(val)}" style="height:30px; font-size:0.8rem;">`;
            }

            wrap.innerHTML = def.tipo === 'casilla'
                ? inputHtml
                : `<label class="saas-label small mb-1">${escapeHtml(def.nombre)}</label>${inputHtml}`;

            const inputEl = wrap.querySelector('.custom-field-input');
            inputEl?.addEventListener('change', function () {
                if (!lead.campos_personalizados || typeof lead.campos_personalizados !== 'object') {
                    lead.campos_personalizados = {};
                }
                lead.campos_personalizados[def.key] = (def.tipo === 'casilla') ? this.checked : this.value;
                updateLeadCrmAjax(lead.id, { campos_personalizados: lead.campos_personalizados });
            });

            crmCustomFieldsContainer.appendChild(wrap);
        });
    }

    function renderLeadTags(lead) {
        if (!crmTagsContainer) return;
        const tags = Array.isArray(lead.etiquetas) ? lead.etiquetas : [];
        if (tags.length === 0) {
            crmTagsContainer.innerHTML = '<span class="text-muted small">Sin etiquetas</span>';
            return;
        }
        crmTagsContainer.innerHTML = '';
        tags.forEach(tag => {
            const chip = document.createElement('span');
            chip.className = 'crm-tag-chip';
            chip.innerHTML = `${escapeHtml(tag)} <button type="button" title="Quitar">&times;</button>`;
            chip.querySelector('button').addEventListener('click', function () {
                lead.etiquetas = lead.etiquetas.filter(t => t !== tag);
                renderLeadTags(lead);
                renderKanbanBoard();
                updateLeadCrmAjax(lead.id, { etiquetas: lead.etiquetas });
            });
            crmTagsContainer.appendChild(chip);
        });
    }

    function renderLeadTasks(lead) {
        if (!crmTasksContainer) return;
        const tareas = Array.isArray(lead.tareas) ? lead.tareas : [];
        if (tareas.length === 0) {
            crmTasksContainer.innerHTML = '<div class="text-muted small">Sin tareas pendientes.</div>';
            return;
        }
        crmTasksContainer.innerHTML = '';
        tareas.forEach(t => {
            const row = document.createElement('div');
            row.className = `crm-task-item ${t.completada ? 'is-done' : ''}`;
            row.innerHTML = `
                <label class="d-flex align-items-center gap-2 m-0" style="cursor: pointer; flex: 1;">
                    <input type="checkbox" ${t.completada ? 'checked' : ''}>
                    <span><strong>[${escapeHtml((t.tipo || 'seguimiento').toUpperCase())}]</strong> ${escapeHtml(t.titulo)}</span>
                </label>
                <button type="button" class="btn btn-xs text-muted p-0 border-0" title="Eliminar tarea">&times;</button>
            `;
            row.querySelector('input').addEventListener('change', function () {
                manageTaskAjax(lead.id, { accion: 'toggle', tarea_id: t.id });
            });
            row.querySelector('button').addEventListener('click', function () {
                manageTaskAjax(lead.id, { accion: 'eliminar', tarea_id: t.id });
            });
            crmTasksContainer.appendChild(row);
        });
    }

    function renderLeadNotes(lead) {
        if (!crmNotesContainer) return;
        const notas = Array.isArray(lead.notas) ? lead.notas : [];
        if (notas.length === 0) {
            crmNotesContainer.innerHTML = '<div class="text-muted small">Sin notas internas registradas.</div>';
            return;
        }
        crmNotesContainer.innerHTML = '';
        notas.forEach(n => {
            const card = document.createElement('div');
            card.className = 'crm-note-card';
            card.innerHTML = `
                <div class="crm-note-meta">
                    <span><i class="fas fa-user-edit mr-1"></i>${escapeHtml(n.autor || 'Asesor')}</span>
                    <span>
                        ${escapeHtml(n.fecha || '')}
                        <button type="button" class="btn btn-xs text-danger p-0 ml-1 border-0" title="Eliminar nota">&times;</button>
                    </span>
                </div>
                <div>${escapeHtml(n.texto)}</div>
            `;
            card.querySelector('button').addEventListener('click', function () {
                manageNoteAjax(lead.id, { accion: 'eliminar', nota_id: n.id });
            });
            crmNotesContainer.appendChild(card);
        });
    }

    // Cotizador rápido vinculado al catálogo de productos
    function renderLeadQuotedProducts(lead) {
        const selectProd = document.getElementById('crm-select-catalog-product');
        const listEl = document.getElementById('crm-lead-products-list');
        const totalEl = document.getElementById('crm-lead-products-total');

        if (selectProd) {
            selectProd.innerHTML = '<option value="">-- Seleccionar producto del catálogo --</option>';
            catalog.forEach(p => {
                selectProd.innerHTML += `<option value="${escapeHtml(p.id)}">${escapeHtml(p.nombre)} (${formatCurrency(p.precio)})</option>`;
            });
        }

        if (!listEl) return;
        const items = Array.isArray(lead.productos_cotizados) ? lead.productos_cotizados : [];
        if (items.length === 0) {
            listEl.innerHTML = '<div class="text-muted small py-2">No hay productos vinculados a este lead aún.</div>';
            if (totalEl) totalEl.textContent = '$0';
            return;
        }

        let sum = 0;
        listEl.innerHTML = '';
        items.forEach((item, idx) => {
            const sub = (Number(item.precio) || 0) * (Number(item.cantidad) || 1);
            sum += sub;
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center py-1 border-bottom small';
            row.innerHTML = `
                <div>
                    <div class="font-weight-bold text-dark">${escapeHtml(item.nombre)}</div>
                    <div class="text-muted font-mono">${item.cantidad} x ${formatCurrency(item.precio)}</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <strong class="font-mono">${formatCurrency(sub)}</strong>
                    <button type="button" class="btn btn-xs text-danger border-0 p-0" data-idx="${idx}">&times;</button>
                </div>
            `;
            row.querySelector('button').addEventListener('click', function () {
                lead.productos_cotizados.splice(idx, 1);
                lead.valor_estimado = lead.productos_cotizados.reduce((a, b) => a + (Number(b.precio) * Number(b.cantidad)), 0);
                renderActiveLeadInspector();
                updateLeadCrmAjax(lead.id, { productos_cotizados: lead.productos_cotizados });
            });
            listEl.appendChild(row);
        });

        if (totalEl) totalEl.textContent = formatCurrency(sum);
    }

    document.getElementById('crm-btn-add-product-to-lead')?.addEventListener('click', function () {
        const lead = getActiveLead();
        const selectProd = document.getElementById('crm-select-catalog-product');
        const qtyInput = document.getElementById('crm-input-product-qty');
        if (!lead || !selectProd || !selectProd.value) return;

        const prod = catalog.find(p => p.id === selectProd.value);
        if (!prod) return;

        const qty = Math.max(1, Number(qtyInput?.value) || 1);
        if (!Array.isArray(lead.productos_cotizados)) lead.productos_cotizados = [];

        lead.productos_cotizados.push({
            producto_id: prod.id,
            nombre: prod.nombre,
            precio: Number(prod.precio) || 0,
            cantidad: qty
        });

        lead.valor_estimado = lead.productos_cotizados.reduce((a, b) => a + (Number(b.precio) * Number(b.cantidad)), 0);
        renderActiveLeadInspector();
        updateLeadCrmAjax(lead.id, { productos_cotizados: lead.productos_cotizados });
    });

    function renderLeadQuotesHistory(lead) {
        const listEl = document.getElementById('crm-lead-quotes-history-list');
        if (!listEl) return;
        const quotes = Array.isArray(lead.cotizaciones_historial) ? lead.cotizaciones_historial : [];
        if (quotes.length === 0) {
            listEl.innerHTML = '<div class="text-muted small py-1">Sin cotizaciones registradas para este lead.</div>';
            return;
        }

        listEl.innerHTML = '';
        quotes.slice().reverse().forEach(q => {
            const item = document.createElement('div');
            item.className = 'crm-quote-history-item';
            item.innerHTML = `
                <div>
                    <div class="font-weight-bold text-dark font-mono small">${escapeHtml(q.folio)}</div>
                    <div class="text-muted" style="font-size:0.7rem;">${escapeHtml(q.fecha || '')} &bull; <strong class="text-success">${formatCurrency(q.total)} ${escapeHtml(q.moneda || 'USD')}</strong></div>
                </div>
                <div>
                    <a href="${escapeHtml(q.url)}" target="_blank" class="btn btn-xs btn-outline-primary" title="Ver documento PDF">
                        <i class="fas fa-file-pdf text-danger mr-1"></i> Ver PDF
                    </a>
                </div>
            `;
            listEl.appendChild(item);
        });
    }

    function renderLeadTimeline(lead) {
        const feedEl = document.getElementById('crm-timeline-feed');
        const countEl = document.getElementById('crm-timeline-count');
        if (!feedEl) return;

        const events = Array.isArray(lead.timeline_actividades) ? lead.timeline_actividades : [];
        if (countEl) countEl.textContent = `${events.length} evento${events.length === 1 ? '' : 's'}`;

        if (events.length === 0) {
            feedEl.innerHTML = '<div class="text-muted small py-4 text-center">No hay actividades registradas aún en el timeline.</div>';
            return;
        }

        feedEl.innerHTML = '';
        events.slice().reverse().forEach(ev => {
            const item = document.createElement('div');
            item.className = 'crm-timeline-item';

            let dotClass = 'dot-creado';
            let iconHtml = '<i class="fas fa-user-plus text-success" style="font-size:0.6rem;"></i>';

            if (ev.tipo === 'cambio_etapa') {
                dotClass = 'dot-cambio_etapa';
                iconHtml = '<i class="fas fa-exchange-alt text-primary" style="font-size:0.6rem;"></i>';
            } else if (ev.tipo === 'asignacion') {
                dotClass = 'dot-asignacion';
                iconHtml = '<i class="fas fa-user-tag text-info" style="font-size:0.6rem;"></i>';
            } else if (ev.tipo === 'cotizacion_enviada' || ev.tipo === 'cotizacion_actualizada') {
                dotClass = 'dot-cotizacion';
                iconHtml = '<i class="fas fa-file-invoice-dollar text-warning" style="font-size:0.6rem;"></i>';
            } else if (ev.tipo === 'api_rest') {
                dotClass = 'dot-api';
                iconHtml = '<i class="fas fa-network-wired text-indigo" style="font-size:0.6rem;"></i>';
            } else if (ev.tipo === 'nota') {
                dotClass = 'dot-asignacion';
                iconHtml = '<i class="fas fa-sticky-note text-secondary" style="font-size:0.6rem;"></i>';
            }

            const formattedDate = ev.fecha ? new Date(ev.fecha).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) : 'Reciente';

            item.innerHTML = `
                <div class="crm-timeline-dot ${dotClass}">${iconHtml}</div>
                <div class="crm-timeline-content">
                    <div class="font-weight-600">${escapeHtml(ev.descripcion || '')}</div>
                    <div class="crm-timeline-meta">
                        <span><i class="fas fa-user mr-1"></i>${escapeHtml(ev.autor || 'Sistema')}</span>
                        <span class="font-mono">${escapeHtml(formattedDate)}</span>
                    </div>
                </div>
            `;
            feedEl.appendChild(item);
        });
    }

    document.getElementById('crm-btn-download-pdf-quote')?.addEventListener('click', function () {
        const lead = getActiveLead();
        if (!lead) return;
        const folio = document.getElementById('crm-quote-folio')?.value.trim() || '';
        const tax = document.getElementById('crm-quote-tax')?.value.trim() || '16';
        const url = `/instancias/${instanciaId}/contactos/${lead.id}/cotizacion/pdf?folio=${encodeURIComponent(folio)}&porcentaje_impuesto=${encodeURIComponent(tax)}`;
        window.open(url, '_blank');
    });

    document.getElementById('crm-btn-send-whatsapp-quote')?.addEventListener('click', async function () {
        const lead = getActiveLead();
        if (!lead) return;
        if (!confirm(`¿Deseas generar y enviar la cotización oficial por WhatsApp a ${lead.nombre} (+${lead.phone_number})?`)) {
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Enviando...';

        try {
            const folio = document.getElementById('crm-quote-folio')?.value.trim() || '';
            const tax = document.getElementById('crm-quote-tax')?.value.trim() || '16';

            const res = await fetch(`/instancias/${instanciaId}/contactos/${lead.id}/cotizacion/enviar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    folio: folio,
                    porcentaje_impuesto: tax
                })
            });

            const data = await res.json();
            if (data.success) {
                alert(data.mensaje || 'Cotización enviada exitosamente por WhatsApp.');
                if (data.contacto) {
                    Object.assign(lead, data.contacto);
                    renderLeadQuotesHistory(lead);
                    renderLeadTimeline(lead);
                    loadConversationMessages(true);
                }
            } else {
                alert(data.error || 'No se pudo enviar la cotización.');
            }
        } catch (e) {
            console.error(e);
            alert('Error de conexión al enviar la cotización por WhatsApp.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fab fa-whatsapp mr-1"></i> Enviar WhatsApp';
        }
    });

    // Popover de atajos rápidos con '/'
    function renderTemplatesPopover() {
        if (!templatesPopover) return;
        if (templates.length === 0) {
            templatesPopover.innerHTML = `<div class="text-muted small p-2">No hay plantillas creadas. Créalas en la sección <strong>Listas &rarr; Plantillas Rápidas</strong>.</div>`;
            return;
        }
        templatesPopover.innerHTML = '<div class="small font-weight-bold text-secondary mb-2 px-1">Selecciona una plantilla rápida:</div>';
        templates.forEach(tpl => {
            const opt = document.createElement('div');
            opt.className = 'crm-template-option';
            opt.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <strong class="text-dark small">${escapeHtml(tpl.titulo)}</strong>
                    <span class="badge badge-light font-mono">${escapeHtml(tpl.atajo)}</span>
                </div>
                <div class="text-muted small text-truncate">${escapeHtml(tpl.contenido)}</div>
            `;
            opt.addEventListener('click', function () {
                const lead = getActiveLead();
                let text = tpl.contenido || '';
                if (lead) {
                    text = text.replace(/\{\{nombre\}\}/g, lead.nombre || 'Cliente')
                        .replace(/\{\{telefono\}\}/g, lead.phone_number || '');
                }
                if (inputInboxMessage) {
                    inputInboxMessage.value = text;
                    inputInboxMessage.focus();
                }
                templatesPopover.classList.remove('is-open');
            });
            templatesPopover.appendChild(opt);
        });
    }

    btnToggleTemplatesPopover?.addEventListener('click', function (e) {
        e.stopPropagation();
        renderTemplatesPopover();
        templatesPopover?.classList.toggle('is-open');
    });

    inputInboxMessage?.addEventListener('input', function () {
        const val = this.value;
        if (val.startsWith('/')) {
            const match = templates.find(t => t.atajo.toLowerCase() === val.trim().toLowerCase());
            if (match) {
                const lead = getActiveLead();
                this.value = (match.contenido || '').replace(/\{\{nombre\}\}/g, lead?.nombre || 'Cliente');
                templatesPopover?.classList.remove('is-open');
            } else {
                renderTemplatesPopover();
                templatesPopover?.classList.add('is-open');
            }
        } else {
            templatesPopover?.classList.remove('is-open');
        }
    });

    // Formateador de texto y markdown enriquecido estilo WhatsApp
    function formatChatContent(rawText) {
        if (!rawText) return '';
        // Filtrar y retirar cualquier emoji
        const cleanText = rawText.replace(/[\u{1F600}-\u{1F64F}\u{1F300}-\u{1F5FF}\u{1F680}-\u{1F6FF}\u{1F700}-\u{1F77F}\u{1F780}-\u{1F7FF}\u{1F800}-\u{1F8FF}\u{1F900}-\u{1F9FF}\u{1FA00}-\u{1FA6F}\u{1FA70}-\u{1FAFF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F1E6}-\u{1F1FF}\u{FE00}-\u{FE0F}\u{200D}]/gu, '');
        let escaped = escapeHtml(cleanText.trim());

        // Convertir **negrita** en <strong>negrita</strong>
        escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Convertir *cursiva* o _cursiva_
        escaped = escaped.replace(/(^|[^*])\*(?!\s)([^*]+?)(?<!\s)\*(?!\*)/g, '$1<em>$2</em>');
        escaped = escaped.replace(/(^|[^_])_(?!\s)([^_]+?)(?<!\s)_(?!_)/g, '$1<em>$2</em>');
        // Convertir URLs en enlaces cliqueables
        escaped = escaped.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener" class="chat-bubble-link">$1</a>');
        // Convertir saltos de línea \n en <br>
        escaped = escaped.replace(/\n/g, '<br>');

        return escaped;
    }

    // Chat en tiempo real, notas internas y copiloto IA
    function formatMessageBubble(msg) {
        const timeStr = msg.created_at
            ? new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            : '';

        if (msg.direction === 'internal_note') {
            const noteBubble = document.createElement('div');
            noteBubble.className = 'chat-bubble-internal-note';
            noteBubble.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:0.7rem; font-weight:700; opacity:0.85;">
                    <span><i class="fas fa-lock mr-1"></i>Nota Interna del Equipo(solo visible para ti)</span>
                    <span>${escapeHtml(timeStr)}</span>
                </div>
                <div>${formatChatContent(msg.body || '')}</div>
            `;
            return noteBubble;
        }

        const isOut = msg.direction === 'outbound';

        let mediaHtml = '';
        if (msg.media_url) {
            if (msg.type === 'image' || msg.type === 'sticker') {
                mediaHtml = `<div class="mb-2"><img src="${escapeHtml(msg.media_url)}" alt="Media" style="max-width: 240px; border-radius: 8px;"></div>`;
            } else if (msg.type === 'audio') {
                mediaHtml = `<div class="mb-2"><audio controls src="${escapeHtml(msg.media_url)}" style="max-width: 240px;"></audio></div>`;
            } else if (msg.type === 'video') {
                mediaHtml = `<div class="mb-2"><video controls src="${escapeHtml(msg.media_url)}" style="max-width: 240px; border-radius: 8px;"></video></div>`;
            } else {
                mediaHtml = `<div class="mb-1"><a href="${escapeHtml(msg.media_url)}" target="_blank" rel="noopener" class="small font-weight-bold"><i class="fas fa-paperclip mr-1"></i>Ver archivo adjunto</a></div>`;
            }
        }

        let statusIconHtml = '';
        if (isOut) {
            if (msg.status === 'read') {
                statusIconHtml = `<i class="fas fa-check-double ml-1 tick-read" title="Leído por el cliente"></i>`;
            } else if (msg.status === 'delivered') {
                statusIconHtml = `<i class="fas fa-check-double ml-1 tick-delivered" title="Entregado"></i>`;
            } else if (msg.status === 'failed') {
                statusIconHtml = `<i class="fas fa-exclamation-triangle text-danger ml-1" title="${escapeHtml(msg.error_message || 'Error de entrega')}"></i>`;
            } else {
                statusIconHtml = `<i class="fas fa-check ml-1 tick-delivered" title="Enviado"></i>`;
            }
        }

        const bubble = document.createElement('div');
        bubble.className = `chat-bubble ${isOut ? 'chat-bubble-outbound' : 'chat-bubble-inbound'}`;
        bubble.innerHTML = `
            ${mediaHtml}
            <div class="chat-bubble-text">${formatChatContent(msg.body || '')}</div>
            <div class="chat-bubble-time">${escapeHtml(timeStr)}${statusIconHtml}</div>
        `;
        return bubble;
    }

    function loadConversationMessages(incremental = false) {
        const lead = getActiveLead();
        if (!lead || !messagesViewport) return;

        if (!incremental) {
            messagesViewport.innerHTML = `<div class="text-center text-muted small py-5"><i class="fas fa-circle-notch fa-spin mr-1"></i> Cargando conversación...</div>`;
        }

        const url = `/instancias/${instanciaId}/contactos/${lead.id}/historial` +
            (incremental && lastMsgId > 0 ? `?after_id=${lastMsgId}` : '');

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                if (data.contacto) {
                    Object.assign(lead, data.contacto);
                } else if (typeof data.is_bot_active === 'boolean') {
                    lead.is_bot_active = data.is_bot_active;
                    if (data.etapa) lead.etapa = data.etapa;
                }

                const msgs = Array.isArray(data.mensajes) ? data.mensajes : [];
                if (!incremental) {
                    messagesViewport.innerHTML = '';
                    if (msgs.length === 0) {
                        messagesViewport.innerHTML = `<div class="text-center text-muted small py-5">No hay mensajes registrados aún con este contacto.</div>`;
                        return;
                    }
                }

                if (msgs.length > 0) {
                    msgs.forEach(m => {
                        if (Number(m.id) > lastMsgId) lastMsgId = Number(m.id);
                        messagesViewport.appendChild(formatMessageBubble(m));
                    });
                    messagesViewport.scrollTop = messagesViewport.scrollHeight;
                }
            })
            .catch(err => console.warn('Error cargando mensajes:', err));
    }

    btnToggleInternalNoteMode?.addEventListener('click', function () {
        isInternalNoteMode = !isInternalNoteMode;
        composerContainer?.classList.toggle('internal-note-mode', isInternalNoteMode);
        if (inputInboxMessage) {
            inputInboxMessage.placeholder = isInternalNoteMode
                ? 'Escribe una nota interna en el hilo(solo visible para ti)...'
                : 'Escribe un mensaje o escribe / para usar una plantilla rápida...';
            inputInboxMessage.focus();
        }
        if (btnInboxSend) {
            btnInboxSend.innerHTML = isInternalNoteMode
                ? '<i class="fas fa-lock mr-1"></i> Guardar Nota'
                : '<i class="fas fa-paper-plane mr-1"></i> Enviar';
        }
    });

    btnInboxAttachMedia?.addEventListener('click', function () {
        inputInboxMediaFile?.click();
    });

    inputInboxMediaFile?.addEventListener('change', function () {
        const file = this.files?.[0];
        if (!file) return;

        attachedMediaFile = file;
        if (crmComposerFileName) crmComposerFileName.textContent = file.name;
        if (crmComposerFileSize) {
            const sizeKb = Math.round(file.size / 1024);
            crmComposerFileSize.textContent = sizeKb > 1024 ? `${(sizeKb / 1024).toFixed(1)} MB` : `${sizeKb} KB`;
        }

        if (crmComposerThumb) {
            const reader = new FileReader();
            reader.onload = function (e) {
                crmComposerThumb.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        crmComposerMediaPreview?.classList.remove('d-none');
        crmComposerMediaPreview?.classList.add('d-flex');
        inputInboxMessage?.focus();
    });

    btnRemoveComposerMedia?.addEventListener('click', function () {
        resetComposerMedia();
    });

    function resetComposerMedia() {
        attachedMediaFile = null;
        if (inputInboxMediaFile) inputInboxMediaFile.value = '';
        if (crmComposerThumb) crmComposerThumb.src = '';
        crmComposerMediaPreview?.classList.add('d-none');
        crmComposerMediaPreview?.classList.remove('d-flex');
    }

    formInboxSend?.addEventListener('submit', function (e) {
        e.preventDefault();
        const lead = getActiveLead();
        const text = inputInboxMessage?.value.trim();
        if (!lead || (!text && !attachedMediaFile)) return;

        if (btnInboxSend) {
            btnInboxSend.disabled = true;
            btnInboxSend.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Enviando...';
        }

        const formData = new FormData();
        formData.append('mensaje', text || '');
        formData.append('es_nota_interna', isInternalNoteMode ? '1' : '0');
        if (attachedMediaFile) {
            formData.append('archivo', attachedMediaFile);
        }

        fetch(`/instancias/${instanciaId}/contactos/${lead.id}/enviar`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    inputInboxMessage.value = '';
                    resetComposerMedia();
                    if (!isInternalNoteMode) {
                        lead.is_bot_active = false;
                    }
                    refreshAll();
                    loadConversationMessages(true);
                } else {
                    alert(data.error || 'No se pudo enviar el mensaje.');
                }
            })
            .catch(err => {
                console.error('Error al enviar mensaje:', err);
                alert('No se pudo completar el envío del mensaje.');
            })
            .finally(() => {
                if (btnInboxSend) {
                    btnInboxSend.disabled = false;
                    btnInboxSend.innerHTML = isInternalNoteMode
                        ? '<i class="fas fa-lock mr-1"></i> Guardar Nota'
                        : '<i class="fas fa-paper-plane mr-1"></i> Enviar';
                }
                inputInboxMessage?.focus();
            });
    });

    selectEstadoConv?.addEventListener('change', function () {
        const lead = getActiveLead();
        if (!lead) return;
        lead.estado_conversacion = this.value;
        updateLeadCrmAjax(lead.id, { estado_conversacion: lead.estado_conversacion });
    });

    selectAsesor?.addEventListener('change', function () {
        const lead = getActiveLead();
        if (!lead) return;
        const val = this.value ? Number(this.value) : null;
        lead.asignado_a = val;
        const adv = advisors.find(a => Number(a.id) === val);
        lead.asesor_nombre = adv ? adv.name : null;
        updateLeadCrmAjax(lead.id, { asignado_a: val });
    });

    btnToggleBot?.addEventListener('click', function () {
        const lead = getActiveLead();
        if (!lead) return;

        fetch(`/instancias/${instanciaId}/contactos/${lead.id}/toggle-bot`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    lead.is_bot_active = Boolean(data.is_bot_active);
                    refreshAll();
                }
            });
    });

    function triggerGenerarResumen() {
        const lead = getActiveLead();
        if (!lead) return;

        const origHtmlBtn = btnIaResumen ? btnIaResumen.innerHTML : '';
        const origHtmlSidebar = btnSidebarGenerateSummary ? btnSidebarGenerateSummary.innerHTML : '';

        if (btnIaResumen) {
            btnIaResumen.disabled = true;
            btnIaResumen.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Analizando...';
        }
        if (btnSidebarGenerateSummary) {
            btnSidebarGenerateSummary.disabled = true;
            btnSidebarGenerateSummary.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Analizando...';
        }
        if (sidebarSummaryBox) {
            sidebarSummaryBox.innerHTML = '<span class="text-primary font-italic"><i class="fas fa-spinner fa-spin mr-1"></i> Generando resumen de la conversación con IA...</span>';
            sidebarSummaryBox.classList.add('is-highlighted');
        }

        const tabMain = document.querySelector('.crm-inspector-tab[data-inspector-tab="main"]');
        if (tabMain && !tabMain.classList.contains('active')) {
            tabMain.click();
        }

        fetch(`/instancias/${instanciaId}/contactos/${lead.id}/ia-resumen`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.resumen_ia) {
                    lead.resumen_ia = data.resumen_ia;
                    if (sidebarSummaryBox) {
                        sidebarSummaryBox.innerHTML = formatSummaryContent(data.resumen_ia);
                        sidebarSummaryBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                } else {
                    if (sidebarSummaryBox) {
                        sidebarSummaryBox.innerHTML = '<span class="text-danger">' + escapeHtml(data.error || 'No se pudo generar el resumen.') + '</span>';
                    }
                }
            })
            .catch(err => {
                if (sidebarSummaryBox) {
                    sidebarSummaryBox.innerHTML = '<span class="text-danger">Error de conexión al generar el resumen.</span>';
                }
            })
            .finally(() => {
                if (btnIaResumen) {
                    btnIaResumen.disabled = false;
                    btnIaResumen.innerHTML = origHtmlBtn;
                }
                if (btnSidebarGenerateSummary) {
                    btnSidebarGenerateSummary.disabled = false;
                    btnSidebarGenerateSummary.innerHTML = origHtmlSidebar;
                }
                setTimeout(() => {
                    sidebarSummaryBox?.classList.remove('is-highlighted');
                }, 1800);
            });
    }

    btnIaResumen?.addEventListener('click', triggerGenerarResumen);
    btnSidebarGenerateSummary?.addEventListener('click', triggerGenerarResumen);

    document.getElementById('btnCopilotSuggest')?.addEventListener('click', function () {
        const lead = getActiveLead();
        if (!lead || !inputInboxMessage) return;

        const btn = this;
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redactando...';

        fetch(`/instancias/${instanciaId}/contactos/${lead.id}/ia-sugerir`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.sugerencia) {
                    inputInboxMessage.value = data.sugerencia;
                    inputInboxMessage.focus();
                }
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = orig;
            });
    });

    document.querySelectorAll('.btn-copilot-tone').forEach(btn => {
        btn.addEventListener('click', function () {
            const lead = getActiveLead();
            const draft = inputInboxMessage?.value.trim();
            const tono = this.dataset.tono || 'ventas';
            if (!lead || !draft) {
                inputInboxMessage?.focus();
                return;
            }

            const orig = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch(`/instancias/${instanciaId}/contactos/${lead.id}/ia-tono`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ borrador: draft, tono: tono })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.mejorado) {
                        inputInboxMessage.value = data.mejorado;
                        inputInboxMessage.focus();
                    }
                })
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = orig;
                });
        });
    });

    function updateLeadCrmAjax(leadId, payload) {
        fetch(`/instancias/${instanciaId}/contactos/${leadId}/crm`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const target = leads.find(l => Number(l.id) === Number(leadId));
                    if (target && data.contacto) {
                        Object.assign(target, data.contacto);
                    }
                    if (target && typeof data.is_bot_active === 'boolean') {
                        target.is_bot_active = data.is_bot_active;
                    }
                    refreshAll();
                }
            });
    }

    crmInputNombre?.addEventListener('change', function () {
        const lead = getActiveLead();
        if (!lead || !this.value.trim()) return;
        lead.nombre = this.value.trim();
        updateLeadCrmAjax(lead.id, { nombre: lead.nombre });
    });

    crmInputEmpresa?.addEventListener('change', function () {
        const lead = getActiveLead();
        if (!lead) return;
        lead.empresa_cliente = this.value.trim();
        updateLeadCrmAjax(lead.id, { empresa_cliente: lead.empresa_cliente });
    });

    crmInputEmail?.addEventListener('change', function () {
        const lead = getActiveLead();
        if (!lead) return;
        lead.email = this.value.trim();
        updateLeadCrmAjax(lead.id, { email: lead.email });
    });

    crmSelectEtapa?.addEventListener('change', function () {
        const lead = getActiveLead();
        if (!lead) return;
        const newStage = this.value;
        const payload = { etapa: newStage };
        if (newStage === 'perdido') {
            const reason = window.prompt('Indica el motivo de pérdida de esta oportunidad (obligatorio):', lead.motivo_perdida || '');
            if (reason === null || !reason.trim()) {
                this.value = lead.etapa || 'nuevo';
                return;
            }
            lead.motivo_perdida = reason.trim();
            payload.motivo_perdida = lead.motivo_perdida;
        }
        lead.etapa = newStage;
        if (lead.etapa === 'asesor') lead.is_bot_active = false;
        updateLeadCrmAjax(lead.id, payload);
    });

    crmInputMotivoPerdida?.addEventListener('change', function () {
        const lead = getActiveLead();
        if (!lead) return;
        lead.motivo_perdida = this.value.trim();
        updateLeadCrmAjax(lead.id, { motivo_perdida: lead.motivo_perdida });
    });

    crmCheckboxOptIn?.addEventListener('change', function () {
        const lead = getActiveLead();
        if (!lead) return;
        lead.opt_in = this.checked;
        updateLeadCrmAjax(lead.id, { opt_in: lead.opt_in });
    });

    crmInputValor?.addEventListener('change', function () {
        const lead = getActiveLead();
        if (!lead) return;
        lead.valor_estimado = Number(this.value) || 0;
        updateLeadCrmAjax(lead.id, { valor_estimado: lead.valor_estimado });
    });

    function addTagFromInput() {
        const lead = getActiveLead();
        const val = crmInputNewTag?.value.trim();
        if (!lead || !val) return;
        if (!Array.isArray(lead.etiquetas)) lead.etiquetas = [];
        if (!lead.etiquetas.includes(val)) {
            lead.etiquetas.push(val);
            crmInputNewTag.value = '';
            updateLeadCrmAjax(lead.id, { etiquetas: lead.etiquetas });
        }
    }

    crmBtnAddTag?.addEventListener('click', addTagFromInput);
    crmInputNewTag?.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addTagFromInput();
        }
    });

    function manageTaskAjax(leadId, payload) {
        fetch(`/instancias/${instanciaId}/contactos/${leadId}/tareas`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const lead = leads.find(l => Number(l.id) === Number(leadId));
                    if (lead) {
                        lead.tareas = data.tareas || [];
                        refreshAll();
                    }
                }
            });
    }

    crmBtnAddTask?.addEventListener('click', function () {
        const lead = getActiveLead();
        const title = crmInputTaskTitle?.value.trim();
        const tipo = crmSelectTaskType?.value || 'seguimiento';
        const dateVal = document.getElementById('crm-input-task-date')?.value || '';
        if (!lead || !title) return;
        crmInputTaskTitle.value = '';
        manageTaskAjax(lead.id, { accion: 'crear', titulo: title, tipo: tipo, vencimiento: dateVal || 'Hoy' });
    });

    function manageNoteAjax(leadId, payload) {
        fetch(`/instancias/${instanciaId}/contactos/${leadId}/notas`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const lead = leads.find(l => Number(l.id) === Number(leadId));
                    if (lead) {
                        lead.notas = data.notas || [];
                        renderLeadNotes(lead);
                    }
                }
            });
    }

    crmBtnAddNote?.addEventListener('click', function () {
        const lead = getActiveLead();
        const text = crmInputNoteText?.value.trim();
        if (!lead || !text) return;
        crmInputNoteText.value = '';
        manageNoteAjax(lead.id, { texto: text });
    });

    // =========================================================================
    // Centro de Tareas y Calendario estilo Google
    // =========================================================================
    let calCurrentDate = new Date();
    let calCurrentView = 'month'; // 'month', 'week', 'list'
    let selectedCalendarTask = null;

    function formatYmd(d) {
        if (!(d instanceof Date) || isNaN(d)) return '';
        const pad = n => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    }

    function parseTaskDate(vencimiento) {
        if (!vencimiento) return formatYmd(new Date());
        const v = String(vencimiento).trim().toLowerCase();
        const today = new Date();
        const pad = n => String(n).padStart(2, '0');

        if (v === 'hoy') return formatYmd(today);
        if (v === 'mañana' || v === 'manana') {
            const tom = new Date(today);
            tom.setDate(tom.getDate() + 1);
            return formatYmd(tom);
        }

        const matchIso = vencimiento.match(/\d{4}-\d{2}-\d{2}/);
        if (matchIso) return matchIso[0];

        const matchDmy = vencimiento.match(/(\d{1,2})\/(\d{1,2})\/(\d{4})/);
        if (matchDmy) {
            return `${matchDmy[3]}-${pad(matchDmy[2])}-${pad(matchDmy[1])}`;
        }

        return formatYmd(today);
    }

    function extractTaskTime(vencimiento) {
        if (!vencimiento) return '';
        const match = vencimiento.match(/(\d{1,2}:\d{2})/);
        return match ? match[1] : '';
    }

    function getAllCalendarTasks() {
        const all = [];
        leads.forEach(l => {
            if (Array.isArray(l.tareas)) {
                l.tareas.forEach(t => {
                    all.push({
                        ...t,
                        leadId: l.id,
                        leadNombre: l.nombre || ('Lead #' + l.id),
                        leadPhone: l.phone_number,
                        dateKey: parseTaskDate(t.vencimiento),
                        timeStr: extractTaskTime(t.vencimiento)
                    });
                });
            }
        });

        return all.filter(t => {
            if (tasksFilter === 'pendientes') return !t.completada;
            if (tasksFilter === 'completadas') return Boolean(t.completada);
            return true;
        });
    }

    function renderTasksCenter() {
        // Actualizar selectores de leads
        const leadSelect = document.getElementById('global-task-lead-select');
        const calLeadSelect = document.getElementById('cal-task-lead-select');
        const fillSelect = (sel) => {
            if (!sel) return;
            const curVal = sel.value;
            sel.innerHTML = '<option value="">-- Seleccionar Lead --</option>';
            leads.forEach(l => {
                const opt = document.createElement('option');
                opt.value = l.id;
                opt.textContent = `${l.nombre || 'Sin nombre'} (+${l.phone_number})`;
                sel.appendChild(opt);
            });
            if (curVal) sel.value = curVal;
        };
        fillSelect(leadSelect);
        fillSelect(calLeadSelect);

        // Preseleccionar fecha de hoy en los campos
        const todayStr = formatYmd(new Date());
        const calTaskDateInput = document.getElementById('cal-task-date');
        if (calTaskDateInput && !calTaskDateInput.value) calTaskDateInput.value = todayStr;
        const crmInputTaskDate = document.getElementById('crm-input-task-date');
        if (crmInputTaskDate && !crmInputTaskDate.value) crmInputTaskDate.value = todayStr;
        const globalDue = document.getElementById('global-task-due');
        if (globalDue && !globalDue.value) globalDue.value = todayStr;

        const gridWrapper = document.getElementById('calendar-grid-wrapper');
        const listWrapper = document.getElementById('calendar-list-wrapper');

        if (calCurrentView === 'list') {
            if (gridWrapper) gridWrapper.classList.add('d-none');
            if (listWrapper) listWrapper.classList.remove('d-none');
            renderTasksCenterList();
            return;
        }

        if (gridWrapper) gridWrapper.classList.remove('d-none');
        if (listWrapper) listWrapper.classList.add('d-none');

        if (calCurrentView === 'month') {
            renderGoogleCalendarMonth();
        } else if (calCurrentView === 'week') {
            renderGoogleCalendarWeek();
        }
    }

    function renderGoogleCalendarMonth() {
        const titleEl = document.getElementById('calendarCurrentMonthYear');
        const gridEl = document.getElementById('calendarDaysGrid');
        const headerEl = document.getElementById('calendarWeekdaysHeader');
        if (!gridEl) return;

        if (headerEl) {
            headerEl.innerHTML = `
                <div>LUN</div><div>MAR</div><div>MIÉ</div><div>JUE</div><div>VIE</div><div>SÁB</div><div>DOM</div>
            `;
            headerEl.style.gridTemplateColumns = 'repeat(7, 1fr)';
        }
        gridEl.style.gridTemplateColumns = 'repeat(7, minmax(0, 1fr))';

        const year = calCurrentDate.getFullYear();
        const month = calCurrentDate.getMonth();

        const monthFormatter = new Intl.DateTimeFormat('es-ES', { month: 'long', year: 'numeric' });
        const monthStr = monthFormatter.format(new Date(year, month, 1));
        if (titleEl) {
            titleEl.textContent = monthStr.charAt(0).toUpperCase() + monthStr.slice(1);
        }

        const todayYmd = formatYmd(new Date());
        const firstDay = new Date(year, month, 1);
        const dayOfWeek = firstDay.getDay(); // 0 domingo, 1 lunes...
        const startOffset = (dayOfWeek + 6) % 7; // Lunes = 0
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        const totalCells = (startOffset + daysInMonth > 35) ? 42 : 35;

        const tasks = getAllCalendarTasks();
        const tasksByDate = {};
        tasks.forEach(t => {
            if (!tasksByDate[t.dateKey]) tasksByDate[t.dateKey] = [];
            tasksByDate[t.dateKey].push(t);
        });

        gridEl.innerHTML = '';

        for (let i = 0; i < totalCells; i++) {
            let cellDate;
            let isOtherMonth = false;

            if (i < startOffset) {
                const prevDay = daysInPrevMonth - startOffset + i + 1;
                cellDate = new Date(year, month - 1, prevDay);
                isOtherMonth = true;
            } else if (i >= startOffset && i < startOffset + daysInMonth) {
                const curDay = i - startOffset + 1;
                cellDate = new Date(year, month, curDay);
                isOtherMonth = false;
            } else {
                const nextDay = i - (startOffset + daysInMonth) + 1;
                cellDate = new Date(year, month + 1, nextDay);
                isOtherMonth = true;
            }

            const cellYmd = formatYmd(cellDate);
            const isToday = cellYmd === todayYmd;
            const dayNum = cellDate.getDate();

            const cell = document.createElement('div');
            cell.className = `crm-calendar-day ${isOtherMonth ? 'is-other-month' : ''} ${isToday ? 'is-today' : ''}`;
            cell.dataset.date = cellYmd;

            const dayTasks = tasksByDate[cellYmd] || [];

            const header = document.createElement('div');
            header.className = 'crm-calendar-day-header';
            header.innerHTML = `<span class="crm-calendar-day-number">${dayNum}</span>`;
            cell.appendChild(header);

            const eventsList = document.createElement('div');
            eventsList.className = 'crm-calendar-events-list';

            const maxVisible = 3;
            const visibleTasks = dayTasks.slice(0, maxVisible);
            const remainingCount = dayTasks.length - maxVisible;

            visibleTasks.forEach(task => {
                const chip = document.createElement('div');
                const tipoClass = 'type-' + (task.tipo || 'seguimiento');
                chip.className = `crm-calendar-chip ${tipoClass} ${task.completada ? 'is-done' : ''}`;
                
                const timePrefix = task.timeStr ? `<strong>${escapeHtml(task.timeStr)}</strong> ` : '';
                const iconMap = {
                    'seguimiento': 'fa-check',
                    'llamada': 'fa-phone-alt',
                    'reunion': 'fa-users',
                    'cotizacion': 'fa-file-invoice-dollar'
                };
                const iconClass = iconMap[task.tipo] || 'fa-calendar';

                chip.innerHTML = `
                    <i class="fas ${iconClass}" style="font-size:0.65rem; opacity:0.85;"></i>
                    <span class="text-truncate">${timePrefix}${escapeHtml(task.titulo)} (${escapeHtml(task.leadNombre)})</span>
                `;

                chip.addEventListener('click', (e) => {
                    e.stopPropagation();
                    openTaskDetailModal(task);
                });

                eventsList.appendChild(chip);
            });

            if (remainingCount > 0) {
                const moreChip = document.createElement('div');
                moreChip.className = 'crm-calendar-more-chip';
                moreChip.textContent = `+${remainingCount} más`;
                moreChip.addEventListener('click', (e) => {
                    e.stopPropagation();
                    openCreateTaskModal(cellYmd);
                });
                eventsList.appendChild(moreChip);
            }

            cell.appendChild(eventsList);

            cell.addEventListener('click', () => {
                openCreateTaskModal(cellYmd);
            });

            gridEl.appendChild(cell);
        }
    }

    function renderGoogleCalendarWeek() {
        const titleEl = document.getElementById('calendarCurrentMonthYear');
        const gridEl = document.getElementById('calendarDaysGrid');
        const headerEl = document.getElementById('calendarWeekdaysHeader');
        if (!gridEl) return;

        const cur = new Date(calCurrentDate);
        const dayOfWeek = (cur.getDay() + 6) % 7; // Lunes = 0
        const monday = new Date(cur);
        monday.setDate(cur.getDate() - dayOfWeek);

        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);

        const dFmt = new Intl.DateTimeFormat('es-ES', { day: 'numeric', month: 'short' });
        const yFmt = new Intl.DateTimeFormat('es-ES', { year: 'numeric' });
        if (titleEl) {
            titleEl.textContent = `Semana ${dFmt.format(monday)} - ${dFmt.format(sunday)}, ${yFmt.format(sunday)}`;
        }

        const todayYmd = formatYmd(new Date());
        const tasks = getAllCalendarTasks();
        const tasksByDate = {};
        tasks.forEach(t => {
            if (!tasksByDate[t.dateKey]) tasksByDate[t.dateKey] = [];
            tasksByDate[t.dateKey].push(t);
        });

        const dayNames = ['LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB', 'DOM'];
        if (headerEl) {
            headerEl.innerHTML = '';
            headerEl.style.gridTemplateColumns = 'repeat(7, 1fr)';
            for (let i = 0; i < 7; i++) {
                const d = new Date(monday);
                d.setDate(monday.getDate() + i);
                const isToday = formatYmd(d) === todayYmd;
                const div = document.createElement('div');
                div.innerHTML = `${dayNames[i]} <span style="font-weight:normal; ${isToday ? 'color:#1a73e8; font-weight:bold;' : ''}">${d.getDate()}</span>`;
                headerEl.appendChild(div);
            }
        }

        gridEl.style.gridTemplateColumns = 'repeat(7, minmax(0, 1fr))';
        gridEl.innerHTML = '';

        for (let i = 0; i < 7; i++) {
            const cellDate = new Date(monday);
            cellDate.setDate(monday.getDate() + i);
            const cellYmd = formatYmd(cellDate);
            const isToday = cellYmd === todayYmd;

            const cell = document.createElement('div');
            cell.className = `crm-calendar-day ${isToday ? 'is-today' : ''}`;
            cell.style.minHeight = '360px';
            cell.dataset.date = cellYmd;

            const header = document.createElement('div');
            header.className = 'crm-calendar-day-header';
            header.innerHTML = `<span class="crm-calendar-day-number">${cellDate.getDate()}</span>`;
            cell.appendChild(header);

            const eventsList = document.createElement('div');
            eventsList.className = 'crm-calendar-events-list';

            const dayTasks = tasksByDate[cellYmd] || [];
            dayTasks.forEach(task => {
                const chip = document.createElement('div');
                const tipoClass = 'type-' + (task.tipo || 'seguimiento');
                chip.className = `crm-calendar-chip ${tipoClass} ${task.completada ? 'is-done' : ''}`;
                chip.style.whiteSpace = 'normal';
                chip.style.padding = '4px 8px';
                
                const timePrefix = task.timeStr ? `<strong>${escapeHtml(task.timeStr)}</strong> ` : '';
                chip.innerHTML = `
                    <div class="font-weight-bold text-truncate">${timePrefix}${escapeHtml(task.titulo)}</div>
                    <div class="small" style="opacity: 0.85; font-size: 0.7rem;">${escapeHtml(task.leadNombre)}</div>
                `;

                chip.addEventListener('click', (e) => {
                    e.stopPropagation();
                    openTaskDetailModal(task);
                });

                eventsList.appendChild(chip);
            });

            cell.appendChild(eventsList);
            cell.addEventListener('click', () => openCreateTaskModal(cellYmd));
            gridEl.appendChild(cell);
        }
    }

    function renderTasksCenterList() {
        const listEl = document.getElementById('crm-global-tasks-list');
        if (!listEl) return;

        const allTasks = getAllCalendarTasks();
        if (allTasks.length === 0) {
            listEl.innerHTML = `<div class="text-center text-muted py-5">No hay tareas con el filtro seleccionado.</div>`;
            return;
        }

        allTasks.sort((a, b) => (a.dateKey || '').localeCompare(b.dateKey || ''));

        listEl.innerHTML = '';
        allTasks.forEach(t => {
            const card = document.createElement('div');
            card.className = `crm-task-board-card ${t.completada ? 'is-done' : ''}`;
            card.innerHTML = `
                <div class="d-flex align-items-center gap-3">
                    <input type="checkbox" ${t.completada ? 'checked' : ''} style="width:18px; height:18px; cursor:pointer;">
                    <div>
                        <div class="font-weight-bold text-dark">
                            <span class="badge badge-info mr-1">${escapeHtml((t.tipo || 'seguimiento').toUpperCase())}</span>
                            ${escapeHtml(t.titulo)}
                        </div>
                        <div class="small text-muted mt-1">
                            Cliente: <a href="#" class="font-weight-bold text-primary btn-jump-lead">${escapeHtml(t.leadNombre)} (+${escapeHtml(t.leadPhone)})</a>
                            &bull; Fecha / Plazo: <strong>${escapeHtml(t.vencimiento || 'Hoy')}</strong>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn-saas btn-saas-secondary btn-sm text-primary btn-edit-global-task" title="Ver detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button type="button" class="btn-saas btn-saas-secondary btn-sm text-danger btn-del-global-task" title="Eliminar">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `;
            card.querySelector('input').addEventListener('change', () => {
                manageTaskAjax(t.leadId, { accion: 'toggle', tarea_id: t.id });
            });
            card.querySelector('.btn-del-global-task').addEventListener('click', () => {
                manageTaskAjax(t.leadId, { accion: 'eliminar', tarea_id: t.id });
            });
            card.querySelector('.btn-edit-global-task').addEventListener('click', () => {
                openTaskDetailModal(t);
            });
            card.querySelector('.btn-jump-lead').addEventListener('click', (e) => {
                e.preventDefault();
                openLeadInInbox(t.leadId);
            });
            listEl.appendChild(card);
        });
    }

    function openCreateTaskModal(prefilledDate) {
        const modalEl = document.getElementById('modalCalendarCreateTask');
        if (!modalEl) return;

        const selLead = document.getElementById('cal-task-lead-select');
        if (selLead && selLead.options.length <= 1) {
            selLead.innerHTML = '<option value="">-- Seleccionar Lead --</option>';
            leads.forEach(l => {
                const opt = document.createElement('option');
                opt.value = l.id;
                opt.textContent = `${l.nombre || 'Sin nombre'} (+${l.phone_number})`;
                selLead.appendChild(opt);
            });
        }

        const dateInput = document.getElementById('cal-task-date');
        if (dateInput) {
            dateInput.value = prefilledDate || formatYmd(new Date());
        }

        const titleInput = document.getElementById('cal-task-title');
        if (titleInput) titleInput.value = '';

        const timeInput = document.getElementById('cal-task-time');
        if (timeInput) timeInput.value = '';

        const descInput = document.getElementById('cal-task-due-desc');
        if (descInput) descInput.value = '';

        const activeLead = getActiveLead();
        if (selLead && activeLead) {
            selLead.value = String(activeLead.id);
        }

        if (window.$) window.$('#modalCalendarCreateTask').modal('show');
    }

    function openTaskDetailModal(task) {
        selectedCalendarTask = task;
        const modalEl = document.getElementById('modalCalendarTaskDetail');
        if (!modalEl) return;

        const titleEl = document.getElementById('cal-detail-title');
        const badgeEl = document.getElementById('cal-detail-type-badge');
        const dateEl = document.getElementById('cal-detail-date');
        const leadNameEl = document.getElementById('cal-detail-lead-name');
        const leadPhoneEl = document.getElementById('cal-detail-lead-phone');
        const chkDone = document.getElementById('cal-detail-toggle-done');

        if (titleEl) titleEl.textContent = task.titulo || 'Tarea sin título';
        if (badgeEl) {
            badgeEl.className = 'badge type-' + (task.tipo || 'seguimiento');
            badgeEl.textContent = (task.tipo || 'seguimiento').toUpperCase();
        }
        if (dateEl) {
            dateEl.textContent = `Fecha / Plazo: ${task.vencimiento || 'Hoy'}`;
        }
        if (leadNameEl) leadNameEl.textContent = task.leadNombre || 'Cliente';
        if (leadPhoneEl) leadPhoneEl.textContent = `+${task.leadPhone || ''}`;
        if (chkDone) chkDone.checked = Boolean(task.completada);

        if (window.$) window.$('#modalCalendarTaskDetail').modal('show');
    }

    // Controles de Navegación del Calendario
    document.getElementById('btnCalendarToday')?.addEventListener('click', () => {
        calCurrentDate = new Date();
        renderTasksCenter();
    });

    document.getElementById('btnCalendarPrev')?.addEventListener('click', () => {
        if (calCurrentView === 'month') {
            calCurrentDate.setMonth(calCurrentDate.getMonth() - 1);
        } else if (calCurrentView === 'week') {
            calCurrentDate.setDate(calCurrentDate.getDate() - 7);
        }
        renderTasksCenter();
    });

    document.getElementById('btnCalendarNext')?.addEventListener('click', () => {
        if (calCurrentView === 'month') {
            calCurrentDate.setMonth(calCurrentDate.getMonth() + 1);
        } else if (calCurrentView === 'week') {
            calCurrentDate.setDate(calCurrentDate.getDate() + 7);
        }
        renderTasksCenter();
    });

    document.getElementById('btnCalendarOpenCreate')?.addEventListener('click', () => {
        openCreateTaskModal();
    });

    // Selector de Vistas de Calendario (Mes, Semana, Lista)
    document.querySelectorAll('[data-calendar-view]').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-calendar-view]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            calCurrentView = this.dataset.calendarView;
            renderTasksCenter();
        });
    });

    // Formulario Modal de creación de tarea
    document.getElementById('formCalendarCreateTask')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const leadId = document.getElementById('cal-task-lead-select')?.value;
        const titulo = document.getElementById('cal-task-title')?.value.trim();
        const tipo = document.getElementById('cal-task-type')?.value || 'seguimiento';
        const dateVal = document.getElementById('cal-task-date')?.value;
        const timeVal = document.getElementById('cal-task-time')?.value;
        const descVal = document.getElementById('cal-task-due-desc')?.value.trim();

        if (!leadId || !titulo || !dateVal) return;

        let venc = dateVal;
        if (timeVal) venc += ' ' + timeVal;
        if (descVal) venc += ' (' + descVal + ')';

        if (window.$) window.$('#modalCalendarCreateTask').modal('hide');
        manageTaskAjax(Number(leadId), { accion: 'crear', titulo: titulo, tipo: tipo, vencimiento: venc });
    });

    // Modal de Detalle: Marcar como completada
    document.getElementById('cal-detail-toggle-done')?.addEventListener('change', function () {
        if (!selectedCalendarTask) return;
        manageTaskAjax(selectedCalendarTask.leadId, { accion: 'toggle', tarea_id: selectedCalendarTask.id });
        selectedCalendarTask.completada = this.checked;
    });

    // Modal de Detalle: Eliminar
    document.getElementById('btnCalDetailDelete')?.addEventListener('click', function () {
        if (!selectedCalendarTask) return;
        if (window.$) window.$('#modalCalendarTaskDetail').modal('hide');
        manageTaskAjax(selectedCalendarTask.leadId, { accion: 'eliminar', tarea_id: selectedCalendarTask.id });
    });

    // Modal de Detalle: Saltar al chat del lead
    document.getElementById('btnCalDetailJumpChat')?.addEventListener('click', function () {
        if (!selectedCalendarTask) return;
        if (window.$) window.$('#modalCalendarTaskDetail').modal('hide');
        openLeadInInbox(selectedCalendarTask.leadId);
    });

    document.querySelectorAll('[data-tasks-filter]').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-tasks-filter]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            tasksFilter = this.dataset.tasksFilter;
            renderTasksCenter();
        });
    });

    document.getElementById('formGlobalCreateTask')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const leadId = document.getElementById('global-task-lead-select')?.value;
        const titulo = document.getElementById('global-task-title')?.value.trim();
        const tipo = document.getElementById('global-task-type')?.value || 'seguimiento';
        const venc = document.getElementById('global-task-due')?.value.trim() || 'Hoy';
        if (!leadId || !titulo) return;
        document.getElementById('global-task-title').value = '';
        manageTaskAjax(Number(leadId), { accion: 'crear', titulo: titulo, tipo: tipo, vencimiento: venc });
    });

    // Listas auxiliares: contactos, catálogo, plantillas, difusiones, campos, importación, conocimiento y widget
    document.querySelectorAll('[data-lists-subtab]').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-lists-subtab]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const target = this.dataset.listsSubtab;
            ['contactos', 'catalogo', 'plantillas', 'difusion', 'campos', 'importar', 'conocimiento', 'widget'].forEach(k => {
                document.getElementById(`subpane-${k}`)?.classList.toggle('d-none', k !== target);
            });
            if (target === 'conocimiento') renderKnowledgeBase();
            if (target === 'widget') updateWidgetPreview();
        });
    });

    function renderListsCenter() {
        // Directorio de contactos
        const tbody = document.getElementById('crm-directory-tbody');
        if (tbody) {
            const q = directorySearchQuery.toLowerCase();
            const filtered = leads.filter(l => {
                if (!q) return true;
                return (l.nombre || '').toLowerCase().includes(q) ||
                    (l.empresa_cliente || '').toLowerCase().includes(q) ||
                    (l.phone_number || '').toLowerCase().includes(q) ||
                    (l.email || '').toLowerCase().includes(q);
            });

            tbody.innerHTML = '';
            filtered.forEach(l => {
                const st = stages[l.etapa] || stages.nuevo || { label: 'Nuevo', color: '#3b82f6' };
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="font-weight-bold text-dark">${escapeHtml(l.nombre)}</td>
                    <td>${escapeHtml(l.empresa_cliente || '—')}</td>
                    <td class="font-mono text-success">+${escapeHtml(l.phone_number)}</td>
                    <td>${escapeHtml(l.email || '—')}</td>
                    <td><span class="crm-badge" style="background:${escapeHtml(st.color)}18; color:${escapeHtml(st.color)}">${escapeHtml(st.label)}</span></td>
                    <td class="font-mono font-weight-bold">${formatCurrency(l.valor_estimado)}</td>
                    <td>${(l.etiquetas || []).map(t => `<span class="crm-badge crm-badge-tag mr-1">${escapeHtml(t)}</span>`).join('') || '—'}</td>
                    <td class="text-right">
                        <button type="button" class="btn-saas btn-saas-secondary btn-sm py-1 px-2 btn-dir-chat">
                            <i class="fas fa-comments text-primary mr-1"></i> Abrir
                        </button>
                    </td>
                `;
                tr.querySelector('.btn-dir-chat').addEventListener('click', () => openLeadInInbox(l.id));
                tbody.appendChild(tr);
            });
        }

        // Catálogo de productos
        const catTbody = document.getElementById('crm-catalog-tbody');
        if (catTbody) {
            catTbody.innerHTML = catalog.length === 0
                ? `<tr><td colspan="6" class="text-center text-muted py-4">No hay productos registrados en el catálogo de esta empresa.</td></tr>`
                : '';
            catalog.forEach(p => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="font-mono small">${escapeHtml(p.sku)}</td>
                    <td class="font-weight-bold text-dark">${escapeHtml(p.nombre)}</td>
                    <td><span class="badge badge-light">${escapeHtml(p.categoria)}</span></td>
                    <td class="font-mono font-weight-bold text-success">${formatCurrency(p.precio)}</td>
                    <td class="small text-muted">${escapeHtml(p.descripcion || '—')}</td>
                    <td class="text-right">
                        <button type="button" class="btn btn-xs btn-outline-danger"><i class="fas fa-trash-alt"></i></button>
                    </td>
                `;
                tr.querySelector('button').addEventListener('click', () => {
                    fetch(`/instancias/${instanciaId}/catalogo`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ accion: 'eliminar', producto_id: p.id })
                    }).then(r => r.json()).then(d => {
                        if (d.success) {
                            catalog = d.catalogo || [];
                            refreshAll();
                        }
                    });
                });
                catTbody.appendChild(tr);
            });
        }

        // Plantillas rápidas
        const tplTbody = document.getElementById('crm-templates-tbody');
        if (tplTbody) {
            tplTbody.innerHTML = templates.length === 0
                ? `<tr><td colspan="4" class="text-center text-muted py-4">Sin plantillas rápidas registradas.</td></tr>`
                : '';
            templates.forEach(t => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><span class="badge badge-dark font-mono">${escapeHtml(t.atajo)}</span></td>
                    <td class="font-weight-bold">${escapeHtml(t.titulo)}</td>
                    <td class="small text-secondary">${escapeHtml(t.contenido)}</td>
                    <td class="text-right">
                        <button type="button" class="btn btn-xs btn-outline-danger"><i class="fas fa-trash-alt"></i></button>
                    </td>
                `;
                tr.querySelector('button').addEventListener('click', () => {
                    fetch(`/instancias/${instanciaId}/plantillas`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ accion: 'eliminar', plantilla_id: t.id })
                    }).then(r => r.json()).then(d => {
                        if (d.success) {
                            templates = d.plantillas || [];
                            refreshAll();
                        }
                    });
                });
                tplTbody.appendChild(tr);
            });
        }

        // Historial de difusiones masivas
        const bcastTbody = document.getElementById('crm-broadcasts-tbody');
        if (bcastTbody) {
            bcastTbody.innerHTML = broadcasts.length === 0
                ? `<tr><td colspan="5" class="text-center text-muted py-4">No se han enviado campañas masivas aún.</td></tr>`
                : '';
            broadcasts.forEach(c => {
                const tr = document.createElement('tr');
                const fallidos = Number(c.fallidos) || 0;
                const excluidos = Number(c.excluidos_opt_out) || 0;
                tr.innerHTML = `
                    <td class="font-weight-bold">${escapeHtml(c.nombre)}</td>
                    <td><span class="badge badge-info">${escapeHtml(c.filtro_etapa)}</span> ${c.filtro_tag ? `<span class="badge badge-secondary">#${escapeHtml(c.filtro_tag)}</span>` : ''}</td>
                    <td class="small text-muted">${escapeHtml(c.mensaje)}</td>
                    <td class="font-mono small">
                        <span class="text-success font-weight-bold">${c.enviados || 0} enviados</span>
                        ${fallidos > 0 ? `<span class="text-danger ml-1">(${fallidos} fallidos)</span>` : ''}
                        ${excluidos > 0 ? `<span class="text-muted ml-1">(${excluidos} opt-out)</span>` : ''}
                    </td>
                    <td class="small text-muted">${escapeHtml(c.fecha)}</td>
                `;
                bcastTbody.appendChild(tr);
            });
        }

        // Campos personalizados de la cuenta
        const cfTbody = document.getElementById('crm-custom-fields-tbody');
        if (cfTbody) {
            cfTbody.innerHTML = (!Array.isArray(customFieldsDef) || customFieldsDef.length === 0)
                ? `<tr><td colspan="5" class="text-center text-muted py-4">No hay campos personalizados definidos para esta empresa.</td></tr>`
                : '';
            customFieldsDef.forEach(cf => {
                const tr = document.createElement('tr');
                const optsStr = Array.isArray(cf.opciones) && cf.opciones.length > 0 ? cf.opciones.join(', ') : '—';
                tr.innerHTML = `
                    <td class="font-mono small">${escapeHtml(cf.key)}</td>
                    <td class="font-weight-bold text-dark">${escapeHtml(cf.nombre)}</td>
                    <td><span class="badge badge-light text-uppercase">${escapeHtml(cf.tipo)}</span></td>
                    <td class="small text-muted">${escapeHtml(optsStr)}</td>
                    <td class="text-right">
                        <button type="button" class="btn btn-xs btn-outline-danger"><i class="fas fa-trash-alt"></i></button>
                    </td>
                `;
                tr.querySelector('button').addEventListener('click', () => {
                    fetch(`/instancias/${instanciaId}/campos-personalizados`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ accion: 'eliminar', field_id: cf.id })
                    }).then(r => r.json()).then(d => {
                        if (d.success) {
                            customFieldsDef = d.campos || [];
                            refreshAll();
                        }
                    });
                });
                cfTbody.appendChild(tr);
            });
        }
    }

    document.getElementById('formCrmCustomField')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const form = this;
        fetch(`/instancias/${instanciaId}/campos-personalizados`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
                accion: 'crear',
                nombre: form.nombre.value.trim(),
                tipo: form.tipo.value,
                opciones: form.opciones?.value.trim() || ''
            })
        }).then(r => r.json()).then(d => {
            if (d.success) {
                customFieldsDef = d.campos || [];
                form.reset();
                refreshAll();
            }
        });
    });

    document.getElementById('inputDirectorySearch')?.addEventListener('input', function () {
        directorySearchQuery = this.value.trim();
        renderListsCenter();
    });

    document.getElementById('formCrmCatalog')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const form = this;
        fetch(`/instancias/${instanciaId}/catalogo`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
                accion: 'crear',
                sku: form.sku.value.trim(),
                nombre: form.nombre.value.trim(),
                categoria: form.categoria.value.trim(),
                precio: Number(form.precio.value) || 0,
                descripcion: form.descripcion.value.trim()
            })
        }).then(r => r.json()).then(d => {
            if (d.success) {
                catalog = d.catalogo || [];
                form.reset();
                refreshAll();
            }
        });
    });

    document.getElementById('formCrmTemplate')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const form = this;
        fetch(`/instancias/${instanciaId}/plantillas`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
                accion: 'crear',
                atajo: form.atajo.value.trim(),
                titulo: form.titulo.value.trim(),
                contenido: form.contenido.value.trim()
            })
        }).then(r => r.json()).then(d => {
            if (d.success) {
                templates = d.plantillas || [];
                form.reset();
                refreshAll();
            }
        });
    });

    document.getElementById('formCrmBroadcast')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const form = this;
        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;

        fetch(`/instancias/${instanciaId}/difusion`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
                nombre_campana: form.nombre_campana.value.trim(),
                filtro_etapa: form.filtro_etapa.value,
                filtro_tag: form.filtro_tag.value.trim(),
                mensaje: form.mensaje.value.trim()
            })
        }).then(r => r.json()).then(d => {
            if (d.success) {
                broadcasts = d.campanas || [];
                form.reset();
                renderListsCenter();
            }
        }).finally(() => {
            if (btn) btn.disabled = false;
        });
    });

    // Importador de contactos desde archivo CSV
    const csvFileInput = document.getElementById('csvFileInput');
    const csvDropZone = document.getElementById('csvDropZone');
    const csvMappingContainer = document.getElementById('csvMappingContainer');
    const csvResultCard = document.getElementById('csvResultCard');
    let csvSelectedFile = null;

    if (csvFileInput) {
        csvFileInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                handleCsvFileSelected(this.files[0]);
            }
        });
    }

    if (csvDropZone) {
        csvDropZone.addEventListener('dragover', function (e) {
            e.preventDefault();
            this.style.borderColor = '#3b82f6';
            this.style.background = '#eff6ff';
        });
        csvDropZone.addEventListener('dragleave', function (e) {
            e.preventDefault();
            this.style.borderColor = '#cbd5e1';
            this.style.background = '#f8fafc';
        });
        csvDropZone.addEventListener('drop', function (e) {
            e.preventDefault();
            this.style.borderColor = '#cbd5e1';
            this.style.background = '#f8fafc';
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                handleCsvFileSelected(e.dataTransfer.files[0]);
            }
        });
    }

    async function handleCsvFileSelected(file) {
        csvSelectedFile = file;
        const infoEl = document.getElementById('csvFileInfo');
        if (infoEl) infoEl.textContent = `Archivo cargado: ${file.name} (${Math.round(file.size / 1024)} KB)`;

        const formData = new FormData();
        formData.append('archivo_csv', file);
        formData.append('modo', 'preview');

        try {
            const res = await fetch(`/instancias/${instanciaId}/contactos/importar`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData
            });
            const data = await res.json();
            if (data.success && Array.isArray(data.columnas)) {
                csvMappingContainer?.classList.remove('d-none');
                csvResultCard?.classList.add('d-none');
                populateCsvMappingSelects(data.columnas);
                renderCsvPreviewTable(data.columnas, data.preview || []);
            } else {
                alert(data.error || 'No se pudieron leer las columnas del CSV.');
            }
        } catch (e) {
            console.error(e);
            alert('Error al procesar el archivo CSV.');
        }
    }

    function populateCsvMappingSelects(columns) {
        const selects = [
            { id: 'mapColPhone', defaultMatch: ['tel', 'phone', 'cel', 'movil', 'whatsapp'] },
            { id: 'mapColName', defaultMatch: ['nom', 'name', 'cliente', 'prospecto'] },
            { id: 'mapColCompany', defaultMatch: ['emp', 'company', 'negocio'] },
            { id: 'mapColEmail', defaultMatch: ['mail', 'correo'] },
            { id: 'mapColTag', defaultMatch: ['tag', 'etiq', 'interes'] },
            { id: 'mapColValue', defaultMatch: ['val', 'presupuesto', 'precio', 'monto'] },
            { id: 'mapColStage', defaultMatch: ['etapa', 'stage', 'fase', 'estado'] }
        ];

        selects.forEach(s => {
            const el = document.getElementById(s.id);
            if (!el) return;
            el.innerHTML = '<option value="">-- No mapear --</option>';
            columns.forEach(col => {
                const opt = document.createElement('option');
                opt.value = col;
                opt.textContent = col;
                const lower = col.toLowerCase();
                if (s.defaultMatch.some(m => lower.includes(m))) {
                    opt.selected = true;
                }
                el.appendChild(opt);
            });
        });
    }

    function renderCsvPreviewTable(headers, rows) {
        const head = document.getElementById('csvPreviewHead');
        const body = document.getElementById('csvPreviewBody');
        if (!head || !body) return;

        head.innerHTML = headers.map(h => `<th>${escapeHtml(h)}</th>`).join('');
        body.innerHTML = '';
        rows.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = r.map(c => `<td>${escapeHtml(c || '')}</td>`).join('');
            body.appendChild(tr);
        });
    }

    document.getElementById('btnRunCsvImport')?.addEventListener('click', async function () {
        if (!csvSelectedFile) {
            alert('Por favor selecciona un archivo CSV primero.');
            return;
        }

        const mapping = {
            telefono: document.getElementById('mapColPhone')?.value || '',
            nombre: document.getElementById('mapColName')?.value || '',
            empresa_cliente: document.getElementById('mapColCompany')?.value || '',
            email: document.getElementById('mapColEmail')?.value || '',
            etiqueta: document.getElementById('mapColTag')?.value || '',
            valor_estimado: document.getElementById('mapColValue')?.value || '',
            etapa: document.getElementById('mapColStage')?.value || ''
        };

        if (!mapping.telefono) {
            alert('Debes seleccionar la columna correspondiente al Teléfono / WhatsApp.');
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Importando...';

        const formData = new FormData();
        formData.append('archivo_csv', csvSelectedFile);
        formData.append('modo', 'import');
        formData.append('mapeo', JSON.stringify(mapping));

        try {
            const res = await fetch(`/instancias/${instanciaId}/contactos/importar`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('csvCountCreated').textContent = data.creados || 0;
                document.getElementById('csvCountUpdated').textContent = data.actualizados || 0;
                document.getElementById('csvCountSkipped').textContent = data.omitidos || 0;
                csvResultCard?.classList.remove('d-none');
                csvMappingContainer?.classList.add('d-none');
                setTimeout(() => window.location.reload(), 2000);
            } else {
                alert(data.error || 'Ocurrió un error al importar los contactos.');
            }
        } catch (e) {
            console.error(e);
            alert('Error de conexión al importar.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play mr-1"></i> Iniciar Importación Masiva';
        }
    });

    document.getElementById('btnCancelCsvImport')?.addEventListener('click', function () {
        csvMappingContainer?.classList.add('d-none');
        csvSelectedFile = null;
        if (csvFileInput) csvFileInput.value = '';
        const infoEl = document.getElementById('csvFileInfo');
        if (infoEl) infoEl.textContent = '';
    });

    // Gestión de la base de conocimiento para la IA
    function renderKnowledgeBase() {
        const tbody = document.getElementById('crm-knowledge-tbody');
        if (!tbody) return;

        if (knowledgeBase.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted small py-4">No hay artículos o preguntas frecuentes registradas en la Base de Conocimiento.</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        knowledgeBase.forEach(art => {
            const tr = document.createElement('tr');
            const tags = Array.isArray(art.palabras_clave) ? art.palabras_clave : [];
            const summary = (art.contenido || '').substring(0, 80) + ((art.contenido || '').length > 80 ? '...' : '');

            tr.innerHTML = `
                <td class="font-weight-bold text-dark">${escapeHtml(art.titulo)}</td>
                <td><span class="badge badge-light">${escapeHtml(art.categoria || 'General')}</span></td>
                <td>${tags.map(t => `<span class="badge badge-secondary mr-1">${escapeHtml(t)}</span>`).join('') || '—'}</td>
                <td class="small text-muted">${escapeHtml(summary)}</td>
                <td class="text-right">
                    <button type="button" class="btn btn-xs btn-outline-primary mr-1 btn-edit-knowledge" data-id="${escapeHtml(art.id)}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-danger btn-del-knowledge" data-id="${escapeHtml(art.id)}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;

            tr.querySelector('.btn-edit-knowledge')?.addEventListener('click', () => editKnowledgeArticle(art.id));
            tr.querySelector('.btn-del-knowledge')?.addEventListener('click', () => deleteKnowledgeArticle(art.id));

            tbody.appendChild(tr);
        });
    }

    document.getElementById('btnOpenAddArticleModal')?.addEventListener('click', function () {
        document.getElementById('knowledgeItemId').value = '';
        document.getElementById('knowledgeTitle').value = '';
        document.getElementById('knowledgeCategory').value = '';
        document.getElementById('knowledgeKeywords').value = '';
        document.getElementById('knowledgeContent').value = '';
        document.getElementById('knowledgeDrawerTitle').innerHTML = '<i class="fas fa-plus text-primary mr-1"></i> Añadir Nuevo Artículo de Conocimiento';
        document.getElementById('knowledgeArticleDrawer')?.classList.remove('d-none');
    });

    document.getElementById('btnCancelKnowledgeItem')?.addEventListener('click', function () {
        document.getElementById('knowledgeArticleDrawer')?.classList.add('d-none');
    });

    document.getElementById('formKnowledgeItem')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const id = document.getElementById('knowledgeItemId').value.trim();
        const titulo = document.getElementById('knowledgeTitle').value.trim();
        const categoria = document.getElementById('knowledgeCategory').value.trim();
        const rawKeywords = document.getElementById('knowledgeKeywords').value.trim();
        const contenido = document.getElementById('knowledgeContent').value.trim();

        const palabrasClave = rawKeywords ? rawKeywords.split(',').map(s => s.trim()).filter(Boolean) : [];
        const accion = id ? 'actualizar' : 'agregar';

        try {
            const res = await fetch(`/instancias/${instanciaId}/base-conocimiento`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    accion: accion,
                    id: id || null,
                    titulo: titulo,
                    categoria: categoria,
                    palabras_clave: palabrasClave,
                    contenido: contenido
                })
            });

            const data = await res.json();
            if (data.success) {
                knowledgeBase = data.base_conocimiento || [];
                renderKnowledgeBase();
                document.getElementById('knowledgeArticleDrawer')?.classList.add('d-none');
            } else {
                alert(data.error || 'No se pudo guardar el artículo.');
            }
        } catch (err) {
            console.error(err);
            alert('Error al guardar artículo.');
        }
    });

    function editKnowledgeArticle(artId) {
        const art = knowledgeBase.find(a => a.id === artId);
        if (!art) return;
        document.getElementById('knowledgeItemId').value = art.id;
        document.getElementById('knowledgeTitle').value = art.titulo || '';
        document.getElementById('knowledgeCategory').value = art.categoria || '';
        document.getElementById('knowledgeKeywords').value = (art.palabras_clave || []).join(', ');
        document.getElementById('knowledgeContent').value = art.contenido || '';
        document.getElementById('knowledgeDrawerTitle').innerHTML = '<i class="fas fa-edit text-primary mr-1"></i> Editar Artículo de Conocimiento';
        document.getElementById('knowledgeArticleDrawer')?.classList.remove('d-none');
    }

    async function deleteKnowledgeArticle(artId) {
        if (!confirm('¿Deseas eliminar este artículo de la Base de Conocimiento?')) return;
        try {
            const res = await fetch(`/instancias/${instanciaId}/base-conocimiento`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    accion: 'eliminar',
                    id: artId
                })
            });
            const data = await res.json();
            if (data.success) {
                knowledgeBase = data.base_conocimiento || [];
                renderKnowledgeBase();
            }
        } catch (err) {
            console.error(err);
            alert('Error al eliminar artículo.');
        }
    }

    // Generador de enlaces directos y widget de chat
    function updateWidgetPreview() {
        const phone = (document.getElementById('linkPhone')?.value.trim() || '').replace(/[^0-9]/g, '');
        const msg = document.getElementById('linkMessage')?.value.trim() || '';
        const utmSource = document.getElementById('linkUtmSource')?.value.trim() || 'web';
        const utmCamp = document.getElementById('linkUtmCampaign')?.value.trim() || 'direct';

        let url = `https://wa.me/${phone}`;
        const params = [];
        if (msg) params.push(`text=${encodeURIComponent(msg)}`);
        if (utmSource) params.push(`utm_source=${encodeURIComponent(utmSource)}`);
        if (utmCamp) params.push(`utm_campaign=${encodeURIComponent(utmCamp)}`);
        if (params.length > 0) url += `?${params.join('&')}`;

        const urlInput = document.getElementById('linkResultUrl');
        if (urlInput) urlInput.value = url;

        const snippetEl = document.getElementById('widgetSnippetCode');
        if (snippetEl) {
            snippetEl.value = `<!-- Widget WhatsApp Web Chat -->\n<script>\n  window.whatsappChatOptions = {\n    phone: "${phone}",\n    message: "${msg.replace(/"/g, '\\"')}"\n  };\n</script>\n<script src="${window.location.origin}/js/chat-widget.js" async></script>`;
        }
    }

    ['linkPhone', 'linkMessage', 'linkUtmSource', 'linkUtmCampaign'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', updateWidgetPreview);
    });

    document.getElementById('btnCopyLink')?.addEventListener('click', function () {
        const val = document.getElementById('linkResultUrl')?.value;
        if (val) {
            navigator.clipboard.writeText(val);
            alert('¡Enlace wa.me copiado al portapapeles!');
        }
    });

    document.getElementById('btnCopyWidgetCode')?.addEventListener('click', function () {
        const val = document.getElementById('widgetSnippetCode')?.value;
        if (val) {
            navigator.clipboard.writeText(val);
            alert('¡Código embebible del widget copiado al portapapeles!');
        }
    });

    // Pestañas de configuración general de la empresa
    document.querySelectorAll('#settingsModalNav [data-cfg-tab]').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#settingsModalNav [data-cfg-tab]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const target = this.dataset.cfgTab;
            ['general', 'horarios', 'fiscal', 'webhooks', 'usuarios', 'plan'].forEach(k => {
                document.getElementById(`cfg-pane-${k}`)?.classList.toggle('d-none', k !== target);
            });
        });
    });

    // Copiar horario comercial de lunes al resto de la semana laboral
    document.getElementById('btnCopiarHorarioSemana')?.addEventListener('click', function () {
        const lunesStart = document.querySelector('.cfg-day-start[data-day="lunes"]')?.value || '08:00';
        const lunesEnd = document.querySelector('.cfg-day-end[data-day="lunes"]')?.value || '18:00';
        const lunesActive = document.querySelector('.cfg-day-active[data-day="lunes"]')?.checked ?? true;
        ['martes', 'miercoles', 'jueves', 'viernes'].forEach(d => {
            const chk = document.querySelector(`.cfg-day-active[data-day="${d}"]`);
            const s = document.querySelector(`.cfg-day-start[data-day="${d}"]`);
            const e = document.querySelector(`.cfg-day-end[data-day="${d}"]`);
            if (chk) chk.checked = lunesActive;
            if (s) s.value = lunesStart;
            if (e) e.value = lunesEnd;
        });
        alert('¡Horario de Lunes copiado exitosamente a Martes, Miércoles, Jueves y Viernes!');
    });

    document.getElementById('formConfigHorario')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const activo = document.getElementById('cfgHorarioActivo')?.checked;
        const timezone = document.getElementById('cfgHorarioTimezone')?.value;
        const mensaje = document.getElementById('cfgHorarioMensaje')?.value.trim();

        const dias = {};
        document.querySelectorAll('.cfg-day-active').forEach(chk => {
            const dayKey = chk.dataset.day;
            const startInput = document.querySelector(`.cfg-day-start[data-day="${dayKey}"]`);
            const endInput = document.querySelector(`.cfg-day-end[data-day="${dayKey}"]`);
            dias[dayKey] = {
                activo: chk.checked,
                desde: startInput ? startInput.value : '09:00',
                hasta: endInput ? endInput.value : '18:00'
            };
        });

        try {
            const res = await fetch(`/instancias/${instanciaId}/horario`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    activo: activo,
                    timezone: timezone,
                    mensaje_fuera_horario: mensaje,
                    dias: dias
                })
            });
            const data = await res.json();
            if (data.success) {
                alert('Horarios comerciales actualizados exitosamente.');
            } else {
                alert(data.error || 'Error al guardar horarios.');
            }
        } catch (err) {
            console.error(err);
            alert('Error de conexión al guardar horarios.');
        }
    });

    document.getElementById('formConfigFiscal')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        try {
            const res = await fetch(`/instancias/${instanciaId}/datos-fiscales`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    razon_social: document.getElementById('cfgFiscalRazon')?.value.trim(),
                    nit_rfc: document.getElementById('cfgFiscalNit')?.value.trim(),
                    direccion: document.getElementById('cfgFiscalDireccion')?.value.trim(),
                    email_contacto: document.getElementById('cfgFiscalEmail')?.value.trim(),
                    moneda: document.getElementById('cfgFiscalMoneda')?.value,
                    porcentaje_impuesto: Number(document.getElementById('cfgFiscalImpuesto')?.value) || 0,
                    validez_dias_default: Number(document.getElementById('cfgFiscalValidez')?.value) || 15,
                    terminos_condiciones: document.getElementById('cfgFiscalTerminos')?.value.trim()
                })
            });
            const data = await res.json();
            if (data.success) {
                alert('Datos fiscales y de cotización actualizados.');
            } else {
                alert(data.error || 'Error al guardar datos fiscales.');
            }
        } catch (err) {
            console.error(err);
            alert('Error de conexión al guardar datos fiscales.');
        }
    });

    document.getElementById('formConfigWebhook')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const activo = document.getElementById('cfgWebhookActivo')?.checked;
        const url = document.getElementById('cfgWebhookUrl')?.value.trim();
        const secret = document.getElementById('cfgWebhookSecret')?.value.trim();
        const eventos = [];
        document.querySelectorAll('.cfg-webhook-event:checked').forEach(c => eventos.push(c.value));

        try {
            const res = await fetch(`/instancias/${instanciaId}/webhook-saliente`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    activo: activo,
                    url: url,
                    secret_key: secret,
                    eventos: eventos
                })
            });
            const data = await res.json();
            if (data.success) {
                alert('Webhook saliente configurado exitosamente.');
            } else {
                alert(data.error || 'Error al guardar webhook.');
            }
        } catch (err) {
            console.error(err);
            alert('Error de conexión al guardar webhook.');
        }
    });

    document.getElementById('btnCopyApiToken')?.addEventListener('click', function () {
        const val = document.getElementById('cfgApiTokenInput')?.value;
        if (val && val !== 'No generado aún') {
            navigator.clipboard.writeText(val);
            alert('Token de API copiado al portapapeles.');
        }
    });

    document.getElementById('btnRegenerateApiToken')?.addEventListener('click', async function () {
        if (!confirm('¿Deseas generar un nuevo token de API? El token anterior dejará de funcionar inmediatamente.')) return;
        try {
            const res = await fetch(`/instancias/${instanciaId}/api-token`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            const data = await res.json();
            if (data.success && data.api_token) {
                document.getElementById('cfgApiTokenInput').value = data.api_token;
                alert('Nuevo token generado exitosamente.');
            }
        } catch (err) {
            console.error(err);
            alert('Error al regenerar token de API.');
        }
    });

    function refreshAll() {
        renderDashboard();
        renderKanbanBoard();
        renderInboxList();
        renderActiveLeadInspector();
        renderTasksCenter();
        renderListsCenter();
        renderKnowledgeBase();
        updateWidgetPreview();
    }

    setInterval(function () {
        if (document.hidden || !activeLeadId) return;
        const chatsPane = document.getElementById('pane-chats');
        if (chatsPane && chatsPane.classList.contains('active')) {
            loadConversationMessages(true);
        }
    }, 4000);

    refreshAll();
    if (activeLeadId) {
        loadConversationMessages(false);
    }

    window.CrmSuite = {
        refreshAll: refreshAll,
        renderKanban: renderKanbanBoard,
        openLeadInInbox: openLeadInInbox
    };
});

