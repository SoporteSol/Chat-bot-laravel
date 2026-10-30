// Onboarding con QR y enlace para vincular cuentas de WhatsApp Business
(function () {
    const configEl = document.getElementById('meta-signup-config');
    const META_APP_ID = configEl ? configEl.getAttribute('data-app-id') : '';
    let META_CONFIG_ID = configEl ? (configEl.getAttribute('data-config-id') || '') : '';
    const TOKEN_URL = configEl ? configEl.getAttribute('data-token-url') : '';
    const STATUS_URL_BASE = configEl ? configEl.getAttribute('data-status-url') : '';

    let activeOnboardingToken = null;
    let pollingInterval = null;

    // Inicializar SDK de Facebook (para uso en la página de cliente o directo)
    window.fbAsyncInit = function () {
        if (!META_APP_ID) return;

        FB.init({
            appId      : META_APP_ID,
            cookie     : true,
            xfbml      : true,
            version    : 'v22.0'
        });
        console.log("Facebook SDK inicializado con App ID:", META_APP_ID);
    };

    // Genera un token temporal y renderiza el QR para que el cliente lo escanee
    window.generarOnboardingSession = function () {
        if (!TOKEN_URL) return;

        // Resetear vistas
        const boxQR = document.getElementById('boxOnboardingQR');
        const boxSuccess = document.getElementById('boxOnboardingSuccess');
        const qrImg = document.getElementById('qrOnboardingImg');
        const qrSpinner = document.getElementById('qrLoadingSpinner');
        const inputUrl = document.getElementById('inputOnboardingUrl');
        const statusBadge = document.getElementById('qrStatusBadge');
        const statusText = document.getElementById('qrStatusText');

        if (boxQR) boxQR.style.display = 'block';
        if (boxSuccess) boxSuccess.style.display = 'none';
        if (qrImg) qrImg.style.display = 'none';
        if (qrSpinner) qrSpinner.style.display = 'flex';
        if (inputUrl) inputUrl.value = 'Generando enlace único...';
        if (statusBadge) statusBadge.className = 'qr-tracker-badge is-waiting';
        if (statusText) statusText.innerText = 'Esperando a que la empresa complete la vinculación...';

        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(TOKEN_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                activeOnboardingToken = data.token;
                if (qrImg) {
                    qrImg.src = data.qr_url;
                }
                if (inputUrl) {
                    inputUrl.value = data.url;
                }
                const btnWa = document.getElementById('btnEnviarWhatsappLink');
                if (btnWa) {
                    const waText = encodeURIComponent('¡Hola! Por favor abre este enlace oficial para conceder los permisos de WhatsApp Business para nuestro asistente virtual inteligente: ' + data.url);
                    btnWa.href = 'https://api.whatsapp.com/send?text=' + waText;
                }

                // Iniciar polling para detectar cuando el cliente complete el proceso
                iniciarPollingOnboarding(data.token);
            }
        })
        .catch(err => {
            console.error("Error al generar sesión de onboarding:", err);
            if (inputUrl) inputUrl.value = 'Error al generar enlace. Recarga la página.';
        });
    };

    function detenerPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
            console.log("Polling de onboarding detenido.");
        }
    }

    // Consulta periódicamente si el cliente ya completó el registro en Meta
    function iniciarPollingOnboarding(token) {
        if (!STATUS_URL_BASE || !token) return;

        detenerPolling();

        let intentos = 0;
        const maxIntentos = 36; // 3 minutos máximo (36 * 5s)

        pollingInterval = setInterval(() => {
            // Si la pestaña no está activa o el modal ya no es visible, pausar ticks
            const modal = document.getElementById('modalCrearInstancia');
            if (document.hidden || (modal && !modal.classList.contains('show'))) {
                return;
            }

            intentos++;
            if (intentos > maxIntentos) {
                detenerPolling();
                const statusText = document.getElementById('qrStatusText');
                if (statusText) statusText.innerText = 'Tiempo de espera agotado. Vuelve a abrir el modal para un nuevo QR.';
                return;
            }

            fetch(`${STATUS_URL_BASE}/${token}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'completed') {
                        detenerPolling();

                        // Notificar éxito en pantalla
                        const boxQR = document.getElementById('boxOnboardingQR');
                        const boxSuccess = document.getElementById('boxOnboardingSuccess');
                        const nombreEl = document.getElementById('successNombreEmpresa');
                        const btnEntrenamiento = document.getElementById('btnIrEntrenamiento');

                        if (boxQR) boxQR.style.display = 'none';
                        if (boxSuccess) boxSuccess.style.display = 'block';

                        if (nombreEl) {
                            nombreEl.textContent = data.nombre_empresa + (data.telefono ? ` (+${data.telefono})` : '');
                        }

                        if (btnEntrenamiento && data.redirect_url) {
                            btnEntrenamiento.href = data.redirect_url;
                        }

                        // Cuando el usuario cierre el modal de éxito, recargar la vista para mostrar la nueva tarjeta
                        if (window.jQuery) {
                            window.jQuery('#modalCrearInstancia').one('hidden.bs.modal', function () {
                                window.location.reload();
                            });
                        }
                    } else if (data.status === 'expired') {
                        detenerPolling();
                    }
                })
                .catch(err => {
                    console.warn("Polling error:", err);
                });
        }, 5000);
    }

    // Copiar el enlace de invitación
    window.copiarEnlaceOnboarding = function () {
        const input = document.getElementById('inputOnboardingUrl');
        const btnText = document.getElementById('btnCopiarText');
        if (!input) return;

        navigator.clipboard.writeText(input.value).then(() => {
            if (btnText) {
                const originalText = btnText.innerText;
                btnText.innerText = '¡Copiado!';
                setTimeout(() => {
                    btnText.innerText = originalText;
                }, 2000);
            }
        }).catch(err => {
            input.select();
            document.execCommand('copy');
            if (btnText) btnText.innerText = '¡Copiado!';
        });
    };

    // Escuchar la apertura del modal para generar el QR automáticamente
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-target="#modalCrearInstancia"], [href="#modalCrearInstancia"]');
        if (trigger) {
            setTimeout(() => {
                window.generarOnboardingSession();
            }, 250);
        }
    });

    // Detener polling con eventos de jQuery / Bootstrap y nativos
    if (window.jQuery) {
        window.jQuery('#modalCrearInstancia').on('hidden.bs.modal', function () {
            detenerPolling();
        });
    }

    document.addEventListener('click', function (e) {
        const dismiss = e.target.closest('#modalCrearInstancia [data-dismiss="modal"]');
        if (dismiss) {
            detenerPolling();
        }
    });

    window.addEventListener('beforeunload', function () {
        detenerPolling();
    });

    window.cargarPlantillaPrompt = function () {
        const plantilla = `Eres el asistente virtual y asesor comercial oficial de nuestra empresa.
Tu objetivo principal es:
1. Saludar cordial y brevemente al cliente.
2. Responder dudas sobre nuestros productos/servicios, horarios y formas de pago.
3. Si el cliente solicita hablar con un asesor o una persona, confírmale que un agente humano lo atenderá en breve.
4. Mantén las respuestas breves, directas y sin información interna confidencial.`;
        const el = document.getElementById('crear_system_prompt');
        if (el) el.value = plantilla;
    };
})();
