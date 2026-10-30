// Manejadores interactivos del dashboard: modales, pestañas y dropdowns
document.addEventListener('DOMContentLoaded', function () {
    // Manejador de Modales Vanilla
    document.addEventListener('click', function (e) {
        // Abrir modal (data-toggle="modal")
        const trigger = e.target.closest('[data-toggle="modal"]');
        if (trigger) {
            e.preventDefault();
            const targetSelector = trigger.getAttribute('data-target') || trigger.getAttribute('href');
            if (targetSelector) {
                const modal = document.querySelector(targetSelector);
                if (modal) {
                    modal.classList.add('show');
                    modal.style.setProperty('display', 'flex', 'important');
                    document.body.classList.add('modal-open');
                }
            }
            return;
        }

        // Cerrar modal al hacer click en botón cerrar (data-dismiss="modal")
        const dismiss = e.target.closest('[data-dismiss="modal"]');
        if (dismiss) {
            e.preventDefault();
            const modal = dismiss.closest('.modal');
            if (modal) {
                modal.classList.remove('show');
                modal.style.setProperty('display', 'none', 'important');
                if (!document.querySelector('.modal.show')) {
                    document.body.classList.remove('modal-open');
                }
            }
            return;
        }

        // Cerrar modal al hacer click en el fondo oscuro exterior
        if (e.target.classList && e.target.classList.contains('modal')) {
            e.target.classList.remove('show');
            e.target.style.setProperty('display', 'none', 'important');
            if (!document.querySelector('.modal.show')) {
                document.body.classList.remove('modal-open');
            }
            return;
        }

        // Cerrar alertas (data-dismiss="alert")
        const dismissAlert = e.target.closest('[data-dismiss="alert"]');
        if (dismissAlert) {
            e.preventDefault();
            const alert = dismissAlert.closest('.alert');
            if (alert) {
                alert.style.display = 'none';
            }
            return;
        }

        // Manejador de Tabs y Pills Vanilla (data-toggle="pill" o data-toggle="tab")
        const tabTrigger = e.target.closest('[data-toggle="pill"], [data-toggle="tab"]');
        if (tabTrigger) {
            e.preventDefault();
            const targetSelector = tabTrigger.getAttribute('href') || tabTrigger.getAttribute('data-target');
            if (targetSelector) {
                const nav = tabTrigger.closest('.saas-tabs-container, .nav, .sidebar-nav');
                if (nav) {
                    nav.querySelectorAll('.saas-tab-btn, .nav-link, .nav-item').forEach(link => link.classList.remove('active'));
                }
                tabTrigger.classList.add('active');

                // Sincronizar otros selectores idénticos
                document.querySelectorAll(`[data-toggle="pill"][href="${targetSelector}"], [data-toggle="tab"][href="${targetSelector}"]`).forEach(el => {
                    el.classList.add('active');
                });

                const targetPane = document.querySelector(targetSelector);
                if (targetPane && targetPane.parentElement) {
                    targetPane.parentElement.querySelectorAll('.tab-pane').forEach(pane => {
                        pane.classList.remove('show', 'active');
                    });
                    targetPane.classList.add('show', 'active');
                }
            }
            return;
        }

        // Manejador de Dropdowns Vanilla (data-toggle="dropdown")
        const dropdownTrigger = e.target.closest('[data-toggle="dropdown"]');
        if (dropdownTrigger) {
            e.preventDefault();
            const dropdown = dropdownTrigger.closest('.dropdown');
            if (dropdown) {
                const menu = dropdown.querySelector('.dropdown-menu');
                if (menu) {
                    const isShown = menu.classList.contains('show');
                    document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
                    if (!isShown) {
                        menu.classList.add('show');
                    }
                }
            }
        } else if (!e.target.closest('.dropdown-menu')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
        }
    });

    // Cerrar modal con la tecla Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(m => {
                m.classList.remove('show');
                m.style.setProperty('display', 'none', 'important');
            });
            document.body.classList.remove('modal-open');
        }
    });
});

// Mini-shim para que $('#modalId').modal('show|hide') funcione sin Bootstrap JS pesado
window.$ = window.$ || function (selector) {
    const el = typeof selector === 'string' ? document.querySelector(selector) : selector;
    return {
        modal: function (action) {
            if (!el) return;
            if (action === 'show') {
                el.classList.add('show');
                el.style.setProperty('display', 'flex', 'important');
                document.body.classList.add('modal-open');
            } else if (action === 'hide') {
                el.classList.remove('show');
                el.style.setProperty('display', 'none', 'important');
                if (!document.querySelector('.modal.show')) {
                    document.body.classList.remove('modal-open');
                }
            }
        }
    };
};
window.jQuery = window.jQuery || window.$;
