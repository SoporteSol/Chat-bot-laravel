(function() {
    if (document.getElementById('crm-web-chat-bubble')) return;

    var opts = window.whatsappChatOptions || {};
    var phone = opts.phone || '';
    var msg = opts.message || 'Hola, deseo información.';
    var company = opts.company || 'Atención por WhatsApp';

    if (!phone) return;

    var cleanPhone = phone.replace(/[^0-9]/g, '');
    var url = 'https://wa.me/' + cleanPhone + '?text=' + encodeURIComponent(msg);

    var container = document.createElement('div');
    container.id = 'crm-web-chat-bubble';
    container.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:999999;display:flex;align-items:center;gap:12px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;';

    var tooltip = document.createElement('div');
    tooltip.style.cssText = 'background:#ffffff;color:#1e293b;padding:8px 14px;border-radius:12px;font-size:13px;font-weight:600;box-shadow:0 4px 15px rgba(0,0,0,0.12);border:1px solid #e2e8f0;white-space:nowrap;transition:transform 0.2s ease;';
    tooltip.textContent = company;

    var btn = document.createElement('a');
    btn.href = url;
    btn.target = '_blank';
    btn.rel = 'noopener noreferrer';
    btn.style.cssText = 'width:60px;height:60px;border-radius:50%;background:#25D366;box-shadow:0 6px 20px rgba(37,211,102,0.4);display:flex;align-items:center;justify-content:center;color:#ffffff;text-decoration:none;transition:transform 0.2s ease,box-shadow 0.2s ease;cursor:pointer;';
    btn.innerHTML = '<svg width="34" height="34" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91C2.13 13.66 2.59 15.36 3.45 16.86L2.05 22L7.3 20.62C8.75 21.41 10.38 21.83 12.04 21.83C17.5 21.83 21.95 17.38 21.95 11.92C21.95 9.27 20.92 6.78 19.05 4.91C17.18 3.03 14.69 2 12.04 2M12.05 3.67C14.25 3.67 16.31 4.53 17.87 6.09C19.42 7.65 20.28 9.72 20.28 11.92C20.28 16.46 16.58 20.15 12.04 20.15C10.56 20.15 9.11 19.76 7.85 19L7.55 18.83L4.43 19.65L5.26 16.61L5.06 16.29C4.24 14.99 3.81 13.47 3.81 11.91C3.81 7.37 7.5 3.67 12.05 3.67M9.53 7.34C9.36 7.34 9.09 7.4 8.87 7.64C8.65 7.87 8.03 8.45 8.03 9.63C8.03 10.81 8.89 11.95 9.01 12.11C9.13 12.27 10.7 14.7 13.1 15.74C13.67 15.99 14.11 16.13 14.46 16.25C15.03 16.43 15.55 16.4 15.96 16.34C16.42 16.27 17.38 15.76 17.58 15.19C17.78 14.62 17.78 14.14 17.72 14.04C17.66 13.94 17.5 13.88 17.26 13.76C17.02 13.64 15.83 13.06 15.61 12.98C15.39 12.9 15.23 12.86 15.07 13.1C14.91 13.34 14.45 13.88 14.31 14.04C14.17 14.2 14.03 14.22 13.79 14.1C13.55 13.98 12.54 13.65 11.34 12.58C10.41 11.75 9.78 10.72 9.64 10.48C9.5 10.24 9.62 10.11 9.74 9.99C9.85 9.88 9.99 9.7 10.11 9.56C10.23 9.42 10.27 9.32 10.35 9.16C10.43 9 10.39 8.86 10.33 8.74C10.27 8.62 9.81 7.49 9.62 7.02C9.43 6.57 9.24 6.63 9.09 6.62C8.95 6.62 8.79 6.62 8.63 6.62L9.53 7.34Z"/></svg>';

    btn.addEventListener('mouseenter', function() {
        btn.style.transform = 'scale(1.08)';
        tooltip.style.transform = 'translateX(-4px)';
    });
    btn.addEventListener('mouseleave', function() {
        btn.style.transform = 'scale(1)';
        tooltip.style.transform = 'translateX(0)';
    });

    container.appendChild(tooltip);
    container.appendChild(btn);
    document.body.appendChild(container);
})();

