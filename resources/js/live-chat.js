// Chat en vivo con el contacto de WhatsApp desde el modal
(function () {
    let currentChatHistorialUrl = null;
    let currentChatEnviarUrl = null;
    let currentContactoId = null;
    let chatPollTimer = null;
    let lastMessagesSignature = null;
    let userScrolledUp = false;
    let unreadCountWhileScrolled = 0;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isNearBottom(threshold = 80) {
        const container = document.getElementById('modalChatMensajes');
        if (!container) return true;
        const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
        return distanceFromBottom <= threshold;
    }

    function updateScrollBottomButton() {
        const btn = document.getElementById('btnChatScrollBottom');
        const badge = document.getElementById('badgeChatNuevosMensajes');
        if (!btn) return;

        if (userScrolledUp) {
            btn.classList.remove('d-none');
            if (badge) {
                if (unreadCountWhileScrolled > 0) {
                    badge.textContent = unreadCountWhileScrolled > 99 ? '99+' : unreadCountWhileScrolled;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
            }
        } else {
            btn.classList.add('d-none');
            if (badge) badge.classList.add('d-none');
            unreadCountWhileScrolled = 0;
        }
    }

    function scrollToBottom(force = false, behavior = 'auto') {
        const container = document.getElementById('modalChatMensajes');
        if (!container) return;
        if (!force && userScrolledUp) return;

        requestAnimationFrame(() => {
            if (behavior === 'smooth') {
                container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
            } else {
                container.scrollTop = container.scrollHeight;
            }
        });
    }

    function renderMensajeContenido(msg) {
        let contentHtml = '';
        const bodyText = escapeHtml(msg.body || '');
        const mediaUrl = msg.media_url ? escapeHtml(msg.media_url) : null;
        const type = msg.type || 'text';

        if (type === 'location' || (bodyText && bodyText.includes('maps.app.goo.gl'))) {
            const mapUrl = mediaUrl || 'https://www.google.com/maps';
            const cleanBody = bodyText.replace(/^(\[Ubicación\]|\uD83D\uDCCD)\s*/, '').split('\n')[0] || 'Ubicación compartida';
            return `
                <div class="chat-location-wrapper mb-1">
                    <a href="${mapUrl}" target="_blank" rel="noopener noreferrer" class="d-block p-2 rounded border bg-light text-decoration-none" style="max-width: 260px;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="fas fa-map-marker-alt text-danger" style="font-size: 1.15rem;"></i>
                            <strong style="color: #111827; font-size: 0.88rem;">Ubicación</strong>
                        </div>
                        <div style="font-size: 0.8rem; color: #475569; line-height: 1.3;" class="mb-2">
                            ${cleanBody}
                        </div>
                        <div class="d-flex align-items-center gap-1 text-primary" style="font-size: 0.76rem; font-weight: 600;">
                            <i class="fas fa-location-arrow"></i> Ver en Google Maps
                        </div>
                    </a>
                </div>
            `;
        }

        if (mediaUrl) {
            if (type === 'audio') {
                contentHtml += `
                    <div class="chat-audio-wrapper mb-1">
                        <audio controls preload="metadata" style="max-width: 250px; height: 38px; outline: none; border-radius: 20px;">
                            <source src="${mediaUrl}">
                            Tu navegador no soporta el reproductor de audio.
                        </audio>
                    </div>
                `;
                if (bodyText && bodyText !== '[Audio/Nota de voz]') {
                    contentHtml += `
                        <div style="font-size: 0.82rem; color: #475569; font-style: italic; background: rgba(0,0,0,0.03); padding: 5px 8px; border-radius: 6px; border-left: 3px solid #10b981; margin-top: 4px;">
                            <i class="fa-solid fa-microphone mr-1" style="font-size: 0.72rem; color: #10b981;"></i>${bodyText}
                        </div>
                    `;
                }
            } else if (type === 'sticker') {
                contentHtml += `
                    <div class="chat-sticker-wrapper">
                        <img src="${mediaUrl}" alt="Sticker" style="max-width: 140px; max-height: 140px; object-fit: contain; display: block; border-radius: 8px;">
                    </div>
                `;
            } else if (type === 'image') {
                contentHtml += `
                    <div class="chat-image-wrapper mb-1">
                        <a href="${mediaUrl}" target="_blank" rel="noopener noreferrer" title="Ver imagen completa">
                            <img src="${mediaUrl}" alt="Imagen" style="max-width: 230px; max-height: 230px; border-radius: 8px; object-fit: cover; display: block;">
                        </a>
                    </div>
                `;
                if (bodyText && bodyText !== '[Imagen]') {
                    contentHtml += `<div style="font-size: 0.88rem; margin-top: 4px; color: #111827; white-space: pre-wrap;">${bodyText}</div>`;
                }
            } else if (type === 'video') {
                contentHtml += `
                    <div class="chat-video-wrapper mb-1">
                        <video controls style="max-width: 230px; border-radius: 8px; display: block;">
                            <source src="${mediaUrl}">
                        </video>
                    </div>
                `;
                if (bodyText && bodyText !== '[Video]') {
                    contentHtml += `<div style="font-size: 0.88rem; margin-top: 4px; color: #111827; white-space: pre-wrap;">${bodyText}</div>`;
                }
            } else if (type === 'document') {
                contentHtml += `
                    <div class="chat-doc-wrapper mb-1">
                        <a href="${mediaUrl}" target="_blank" class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" style="font-size: 0.8rem;">
                            <i class="fa-solid fa-file-arrow-down text-primary"></i> ${bodyText || 'Descargar archivo'}
                        </a>
                    </div>
                `;
            } else {
                contentHtml += `<div style="font-size: 0.88rem; color: #111827; white-space: pre-wrap;">${bodyText}</div>`;
            }
        } else {
            contentHtml += `<div style="font-size: 0.88rem; color: #111827; white-space: pre-wrap;">${bodyText}</div>`;
        }

        return contentHtml;
    }

    function cargarHistorialMensajes(historialUrl, isSilent = false) {
        const container = document.getElementById('modalChatMensajes');
        if (!container || !historialUrl) return;

        if (!isSilent) {
            container.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-circle-notch fa-spin fa-2x mb-2 text-primary"></i>
                    <p class="small mb-0">Cargando mensajes...</p>
                </div>
            `;
        }

        fetch(historialUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(res => res.json())
            .then(data => {
                if (!data || !data.success) return;
                const mensajes = data.mensajes || [];

                // Si la lista de mensajes no cambió en el sondeo, salimos sin tocar el DOM
                const lastMsg = mensajes[mensajes.length - 1];
                const newSignature = `${mensajes.length}_${lastMsg ? (lastMsg.id || '') + '_' + (lastMsg.created_at || '') : 'empty'}`;

                // Evitamos saltos de scroll innecesarios si estamos en polling pasivo
                if (isSilent && newSignature === lastMessagesSignature) {
                    return;
                }

                const hadPrevious = lastMessagesSignature !== null;
                const hadNewMessages = hadPrevious && (newSignature !== lastMessagesSignature);
                lastMessagesSignature = newSignature;

                if (mensajes.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-5 text-muted">
                            <i class="far fa-comment-dots fa-2x mb-2 text-muted"></i>
                            <p class="small mb-0">No hay mensajes registrados aún en esta conversación.</p>
                        </div>
                    `;
                    return;
                }

                // Guardar la posición previa antes de actualizar
                const wasScrolledUp = userScrolledUp;
                const prevScrollTop = container.scrollTop;

                let html = '';
                mensajes.forEach(msg => {
                    const isInbound = msg.direction === 'inbound';
                    const time = msg.created_at
                        ? new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                        : '';
                    const isSticker = msg.type === 'sticker';
                    const bubbleStyle = isSticker 
                        ? 'background: transparent; box-shadow: none; padding: 2px;' 
                        : (isInbound ? 'background: #ffffff; border-radius: 8px 8px 8px 2px; padding: 8px 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);' : 'background: #d9fdd3; border-radius: 8px 8px 2px 8px; padding: 8px 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);');

                    if (isInbound) {
                        html += `
                            <div class="d-flex justify-content-start mb-2">
                                <div style="max-width: 78%; word-break: break-word; ${bubbleStyle}">
                                    ${renderMensajeContenido(msg)}
                                    <div class="d-flex justify-content-end align-items-center gap-1 mt-1" style="font-size: 0.68rem; color: #6b7280;">
                                        <span>${time}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="d-flex justify-content-end mb-2">
                                <div style="max-width: 78%; word-break: break-word; ${bubbleStyle}">
                                    ${renderMensajeContenido(msg)}
                                    <div class="d-flex justify-content-end align-items-center gap-1 mt-1" style="font-size: 0.68rem; color: #15803d;">
                                        <span>${time}</span>
                                        <i class="fas fa-check-double text-primary" style="font-size: 0.68rem;" title="Enviado"></i>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                });

                container.innerHTML = html;

                if (!isSilent || !wasScrolledUp) {
                    // Carga inicial, recarga manual o el usuario estaba en el fondo
                    scrollToBottom(true);
                    userScrolledUp = false;
                    unreadCountWhileScrolled = 0;
                    updateScrollBottomButton();
                } else {
                    // El usuario estaba revisando el historial: mantener posición y no forzar scroll
                    container.scrollTop = prevScrollTop;
                    if (hadNewMessages) {
                        unreadCountWhileScrolled++;
                    }
                    updateScrollBottomButton();
                }
            })
            .catch(err => {
                console.error("[LiveChat] Error cargando historial:", err);
            });
    }

    function abrirChatModal(contactoData) {
        const nombre = contactoData.nombre || 'Usuario WhatsApp';
        const telefono = contactoData.telefono || '';
        const nodo = contactoData.nodo || 'Inicio';
        const historialUrl = contactoData.historialUrl || '';
        const enviarUrl = contactoData.enviarUrl || '';
        const contactoId = contactoData.contactoId || '';

        currentChatHistorialUrl = historialUrl;
        currentChatEnviarUrl = enviarUrl;
        currentContactoId = contactoId;
        lastMessagesSignature = null;
        userScrolledUp = false;
        unreadCountWhileScrolled = 0;
        updateScrollBottomButton();

        const modal = document.getElementById('modalChatConversacion');
        if (modal) {
            modal.setAttribute('data-enviar-url', enviarUrl);
            modal.setAttribute('data-historial-url', historialUrl);
            modal.setAttribute('data-contacto-id', contactoId);
        }

        const alertEl = document.getElementById('modalChatAlertError');
        if (alertEl) {
            alertEl.classList.add('d-none');
            alertEl.textContent = '';
        }

        const modalNombre = document.getElementById('modalChatNombre');
        const modalTelefono = document.getElementById('modalChatTelefono');
        const modalNodo = document.getElementById('modalChatNodo');
        const modalAvatar = document.getElementById('modalChatAvatar');

        if (modalNombre) modalNombre.textContent = nombre;
        if (modalTelefono) modalTelefono.textContent = telefono;
        if (modalNodo) modalNodo.textContent = nodo;
        if (modalAvatar) modalAvatar.textContent = nombre.charAt(0).toUpperCase();

        cargarHistorialMensajes(historialUrl);

        if (chatPollTimer) clearInterval(chatPollTimer);
        chatPollTimer = setInterval(() => {
            const isVisible = (typeof $ !== 'undefined' && $('#modalChatConversacion').is(':visible')) ||
                (modal && modal.classList.contains('show'));
            if (isVisible && currentChatHistorialUrl) {
                cargarHistorialMensajes(currentChatHistorialUrl, true);
            }
        }, 3500);

        if (typeof $ !== 'undefined' && typeof $.fn.modal !== 'undefined') {
            $('#modalChatConversacion').modal('show');
        } else if (modal) {
            modal.classList.add('show');
            modal.style.setProperty('display', 'flex', 'important');
            document.body.classList.add('modal-open');
        }

        setTimeout(() => {
            document.getElementById('inputChatModalMensaje')?.focus();
        }, 300);
    }

    function enviarMensajeChat(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        const input = document.getElementById('inputChatModalMensaje');
        const btn = document.getElementById('btnChatModalEnviar');
        const alertEl = document.getElementById('modalChatAlertError');
        const container = document.getElementById('modalChatMensajes');
        const modal = document.getElementById('modalChatConversacion');

        if (alertEl) {
            alertEl.classList.add('d-none');
            alertEl.textContent = '';
        }

        const mensaje = (input ? input.value : '').trim();
        if (!mensaje) {
            input?.focus();
            return;
        }

        const instId = modal?.getAttribute('data-instancia-id') || '';
        const contId = currentContactoId || modal?.getAttribute('data-contacto-id') || '';
        const targetUrl = currentChatEnviarUrl ||
            modal?.getAttribute('data-enviar-url') ||
            (instId && contId ? `/instancias/${instId}/contactos/${contId}/enviar` : null);

        if (!targetUrl) {
            if (alertEl) {
                alertEl.textContent = 'Error: No se encontró la ruta de envío para este contacto.';
                alertEl.classList.remove('d-none');
            }
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
            modal?.querySelector('input[name="_token"]')?.value ||
            document.querySelector('input[name="_token"]')?.value || '';

        // Agregamos el mensaje a la vista de inmediato mientras llega el OK del servidor
        const tempId = 'temp-msg-' + Date.now();
        const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        if (container) {
            const emptyWatermark = container.querySelector('.far.fa-comment-dots');
            if (emptyWatermark) container.innerHTML = '';

            const bubble = document.createElement('div');
            bubble.className = 'd-flex justify-content-end mb-2';
            bubble.id = tempId;
            bubble.innerHTML = `
                <div style="max-width: 78%; background: #d9fdd3; border-radius: 8px 8px 2px 8px; padding: 8px 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); word-break: break-word;">
                    <div style="font-size: 0.88rem; color: #111827; white-space: pre-wrap;">${escapeHtml(mensaje)}</div>
                    <div class="d-flex justify-content-end align-items-center gap-1 mt-1" style="font-size: 0.68rem; color: #15803d;">
                        <span>${timeStr}</span>
                        <i class="fas fa-clock text-muted temp-status-icon" style="font-size: 0.68rem;" title="Enviando a WhatsApp..."></i>
                    </div>
                </div>
            `;
            container.appendChild(bubble);
            userScrolledUp = false;
            unreadCountWhileScrolled = 0;
            updateScrollBottomButton();
            scrollToBottom(true);
        }

        // Limpiar input y bloquear botón
        input.value = '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Enviando...';
        }

        fetch(targetUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                mensaje: mensaje,
                _token: csrfToken
            })
        })
            .then(async res => {
                const data = await res.json().catch(() => null);
                if (!res.ok) {
                    const msg = (data && (data.error || data.message)) || `Error del servidor (HTTP ${res.status})`;
                    throw new Error(msg);
                }
                return data;
            })
            .then(data => {
                if (data && data.success) {
                    const tempEl = document.getElementById(tempId);
                    if (tempEl) {
                        const icon = tempEl.querySelector('.temp-status-icon');
                        if (icon) {
                            icon.className = 'fas fa-check-double text-primary';
                            icon.title = 'Entregado a WhatsApp';
                        }
                    }
                    if (currentChatHistorialUrl) {
                        cargarHistorialMensajes(currentChatHistorialUrl, true);
                    }
                } else {
                    throw new Error((data && data.error) || 'No se pudo enviar el mensaje.');
                }
            })
            .catch(err => {
                console.error("[LiveChat] Error enviando mensaje:", err);
                input.value = mensaje;
                const tempEl = document.getElementById(tempId);
                if (tempEl) {
                    const icon = tempEl.querySelector('.temp-status-icon');
                    if (icon) {
                        icon.className = 'fas fa-exclamation-circle text-danger';
                        icon.title = 'Error: ' + err.message;
                    }
                }
                if (alertEl) {
                    alertEl.textContent = 'Error al enviar por WhatsApp: ' + err.message;
                    alertEl.classList.remove('d-none');
                }
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane mr-1"></i> Enviar';
                }
                input?.focus();
            });
    }

    function initLiveChat() {
        // Enlazar botones de abrir chat
        document.querySelectorAll('.btn-abrir-chat-modal').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                abrirChatModal({
                    nombre: this.dataset.contactoNombre || 'Usuario WhatsApp',
                    telefono: this.dataset.contactoTelefono || '',
                    nodo: this.dataset.contactoNodo || 'Inicio',
                    historialUrl: this.dataset.urlHistorial,
                    enviarUrl: this.dataset.urlEnviar,
                    contactoId: this.dataset.contactoId
                });
            });
        });

        // Enlazar evento de scroll en el contenedor de mensajes
        const container = document.getElementById('modalChatMensajes');
        if (container) {
            container.addEventListener('scroll', function () {
                const nearBottom = isNearBottom(80);
                if (nearBottom) {
                    userScrolledUp = false;
                    unreadCountWhileScrolled = 0;
                } else {
                    userScrolledUp = true;
                }
                updateScrollBottomButton();
            }, { passive: true });
        }

        // Enlazar clic en el botón flotante de volver al fondo
        const btnScrollBottom = document.getElementById('btnChatScrollBottom');
        if (btnScrollBottom) {
            btnScrollBottom.addEventListener('click', function (e) {
                e.preventDefault();
                userScrolledUp = false;
                unreadCountWhileScrolled = 0;
                scrollToBottom(true, 'smooth');
                updateScrollBottomButton();
            });
        }

        // Enlazar botón de recarga en el modal
        document.getElementById('btnRecargarChat')?.addEventListener('click', function () {
            if (currentChatHistorialUrl) {
                cargarHistorialMensajes(currentChatHistorialUrl);
            }
        });

        // Enlazar envío de mensaje por submit del form
        const form = document.getElementById('formChatModalEnviar');
        if (form) {
            form.addEventListener('submit', enviarMensajeChat);
        }

        // Enlazar envío por clic explícito en botón Enviar
        const btnEnviar = document.getElementById('btnChatModalEnviar');
        if (btnEnviar) {
            btnEnviar.addEventListener('click', enviarMensajeChat);
        }

        // Enlazar tecla Enter en el input
        const input = document.getElementById('inputChatModalMensaje');
        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    enviarMensajeChat(e);
                }
            });
        }

        // Limpiar polling al cerrar modal
        const modal = document.getElementById('modalChatConversacion');
        if (modal) {
            const cleanupModal = () => {
                if (chatPollTimer) {
                    clearInterval(chatPollTimer);
                    chatPollTimer = null;
                }
                lastMessagesSignature = null;
                userScrolledUp = false;
                unreadCountWhileScrolled = 0;
                updateScrollBottomButton();
            };

            if (typeof $ !== 'undefined') {
                $(modal).on('hidden.bs.modal', cleanupModal);
            }

            modal.querySelectorAll('[data-dismiss="modal"], .close').forEach(c => {
                c.addEventListener('click', cleanupModal);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLiveChat);
    } else {
        initLiveChat();
    }
})();
