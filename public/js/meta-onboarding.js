// Flujo de vinculación de WhatsApp Business con Meta (Embedded Signup)
(function () {
    'use strict';

    let sessionData = null;
    let authData = null;
    let hasSent = false;
    let config = null;

    function getConfig() {
        if (config) return config;
        const el = document.getElementById('onboardingConfig');
        if (!el) {
            console.error('No se encontró el elemento #onboardingConfig con los metadatos.');
            return {};
        }
        config = {
            appId: el.dataset.appId || '',
            configId: el.dataset.configId || '',
            token: el.dataset.token || '',
            completarUrl: el.dataset.completarUrl || '',
            csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        };
        return config;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isTrustedMetaOrigin(origin) {
        if (!origin) return false;
        try {
            const host = new URL(origin).hostname.toLowerCase();
            return host === 'facebook.com' ||
                host.endsWith('.facebook.com') ||
                host === 'fb.com' ||
                host.endsWith('.fb.com') ||
                host === 'messenger.com' ||
                host.endsWith('.messenger.com') ||
                host === 'meta.com' ||
                host.endsWith('.meta.com');
        } catch (e) {
            return false;
        }
    }

    function setStatus(type, html) {
        const alert = document.getElementById('statusAlert');
        if (!alert) return;
        alert.className = 'status-alert ' + type;
        alert.style.display = 'block';
        alert.innerHTML = html;
    }

    // Inicializar Facebook SDK
    window.fbAsyncInit = function () {
        const cfg = getConfig();
        if (!cfg.appId) return;
        FB.init({
            appId: cfg.appId,
            cookie: true,
            xfbml: true,
            version: 'v22.0'
        });
    };

    // Detectar token en URL hash si Meta redirigió en modo pantalla completa
    window.addEventListener('DOMContentLoaded', function () {
        const hash = window.location.hash;
        if (hash && hash.includes('access_token=')) {
            const hashParams = new URLSearchParams(hash.replace(/^#/, ''));
            const accessToken = hashParams.get('access_token');
            if (accessToken) {
                console.log('Token de acceso detectado en URL hash.');
                try {
                    history.replaceState(null, null, window.location.pathname + window.location.search);
                } catch (e) {}

                setStatus('loading', '<i class="fas fa-circle-notch fa-spin mr-1"></i> Autorización de Meta confirmada. Vinculando tu WhatsApp Business...');
                finalizarOnboarding({ access_token: accessToken });
            }
        }

        // Vincular el botón de inicio de conexión
        const btnIniciar = document.getElementById('btnIniciarMeta');
        if (btnIniciar) {
            btnIniciar.addEventListener('click', iniciarConexionMeta);
        }
    });

    // Capturamos el evento postMessage de Meta para obtener el WABA y Phone Number ID
    window.addEventListener('message', function (event) {
        if (!isTrustedMetaOrigin(event.origin)) return;

        let data = event.data;
        if (typeof data === 'string') {
            try { data = JSON.parse(data); } catch (e) { return; }
        }
        if (!data || typeof data !== 'object') return;

        if (data.type === 'WA_EMBEDDED_SIGNUP') {
            if (data.event === 'FINISH') {
                sessionData = {
                    phone_number_id: data.data?.phone_number_id || null,
                    waba_id: data.data?.waba_id || null
                };
                coordinarYEnviar(false);
            } else if (data.event === 'CANCEL') {
                const btn = document.getElementById('btnIniciarMeta');
                if (btn) btn.disabled = false;
                setStatus('error', 'La vinculación fue cancelada en la ventana de Meta.');
            } else if (data.event === 'ERROR') {
                const btn = document.getElementById('btnIniciarMeta');
                if (btn) btn.disabled = false;
                setStatus('error', 'Ocurrió un error en Meta: ' + escapeHtml(data.data?.error_message || 'Verifica los permisos de tu cuenta en Meta.'));
            }
        }
    });

    function iniciarConexionMeta() {
        const btn = document.getElementById('btnIniciarMeta');
        if (typeof FB === 'undefined') {
            alert('El servicio de Meta aún está cargando. Espera 2 segundos y vuelve a intentar.');
            return;
        }

        const cfg = getConfig();
        sessionData = null;
        authData = null;
        hasSent = false;

        setStatus('loading', '<i class="fas fa-spinner fa-spin mr-1"></i> Conectando con Meta, por favor completa los pasos en la ventana emergente...');
        if (btn) btn.disabled = true;

        const safetyTimer = setTimeout(() => {
            if (btn && btn.disabled && !hasSent) {
                btn.disabled = false;
                setStatus('error', 'Si no se abrió la ventana o se bloqueó, usa el enlace de <strong>"Conectar en pantalla completa"</strong> que está abajo.');
            }
        }, 15000);

        const loginOptions = {
            return_scopes: true
        };

        if (cfg.configId && cfg.configId.trim() !== '') {
            loginOptions.config_id = cfg.configId;
            loginOptions.response_type = 'code';
            loginOptions.override_default_response_type = true;
            loginOptions.extras = {
                featureType: 'whatsapp_business_app_onboarding',
                sessionInfoVersion: '3',
                version: 'v3',
                setup: {}
            };
        } else {
            loginOptions.scope = 'whatsapp_business_management,whatsapp_business_messaging,public_profile';
        }

        FB.login(function (response) {
            clearTimeout(safetyTimer);
            console.log('Respuesta de FB.login:', response);

            if (response.authResponse) {
                authData = {
                    code: response.authResponse.code || null,
                    access_token: response.authResponse.accessToken || null
                };

                // Esperar hasta 1000ms por si el postMessage de WA_EMBEDDED_SIGNUP llega justo después
                setTimeout(() => {
                    coordinarYEnviar(true);
                }, 1000);
            } else {
                if (btn) btn.disabled = false;
                setStatus('error', 'El proceso fue cancelado o no se concedieron los permisos. Puedes reintentar o usar el enlace en pantalla completa.');
            }
        }, loginOptions);
    }

    function coordinarYEnviar(forzar) {
        if (hasSent) return;

        const payload = {
            code: authData?.code || null,
            access_token: authData?.access_token || null,
            phone_number_id: sessionData?.phone_number_id || null,
            waba_id: sessionData?.waba_id || null
        };

        if (payload.phone_number_id || forzar) {
            hasSent = true;
            finalizarOnboarding(payload);
        }
    }

    function finalizarOnboarding(payload) {
        const cfg = getConfig();
        setStatus('loading', '<i class="fas fa-circle-notch fa-spin mr-1"></i> Guardando y vinculando tu WhatsApp Business...');

        fetch(cfg.completarUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': cfg.csrfToken
            },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const contentBox = document.querySelector('.card-content');
                    if (contentBox) {
                        contentBox.innerHTML = `
                            <div class="success-box">
                                <div class="success-icon">
                                    <i class="fas fa-check"></i>
                                </div>
                                <h3 style="font-weight: 800; margin-bottom: 8px; font-size: 1.3rem; color: var(--text-main);">
                                    ¡Vinculación Exitosa!
                                </h3>
                                <p style="color: var(--text-sub); font-size: 0.92rem; line-height: 1.5; margin-bottom: 16px;">
                                    Tu WhatsApp Business ha sido conectado exitosamente con <strong>Prime T</strong>. 
                                </p>
                                <p style="color: var(--brand-wa-dark); font-weight: 700; font-size: 0.85rem;">
                                    <i class="fas fa-shield-alt mr-1"></i> Ya puedes cerrar esta ventana con seguridad.
                                </p>
                            </div>
                        `;
                    }
                } else {
                    hasSent = false;
                    const btn = document.getElementById('btnIniciarMeta');
                    if (btn) btn.disabled = false;
                    setStatus('error', escapeHtml(data.message || 'Error al completar la vinculación.'));
                }
            })
            .catch(() => {
                hasSent = false;
                const btn = document.getElementById('btnIniciarMeta');
                if (btn) btn.disabled = false;
                setStatus('error', 'Error de conexión con el servidor. Intenta nuevamente.');
            });
    }

    // Exportamos la función por si se llama directo desde un onclick
    window.iniciarConexionMeta = iniciarConexionMeta;
})();
