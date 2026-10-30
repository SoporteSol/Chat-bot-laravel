# Regla Estricta: Límite Máximo de 1,000 Líneas por Módulo / Archivo

## 1. Declaración de la Regla
**Ningún archivo o módulo en el proyecto debe superar las 1,000 líneas de código**, sin importar su tipo o extensión:
- Lenguajes y extensiones cubiertas: `.php`, `.js`, `.css`, `.blade.php`, `.html`, `.json`, etc.
- Umbral de acción: Si un archivo alcanza o supera las **1,000 líneas**, es **obligatorio y mandatorio** refactorizarlo y dividirlo en submódulos especializados antes de dar cualquier tarea por concluida.

---

## 2. Estrategias de Descomposición por Tecnología

### A. Hojas de Estilo (.css)
Cuando un archivo CSS supere las 1,000 líneas:
1. **Descomposición por Dominio Funcional:**
   - `base.css`: Variables CSS tokens (`:root`), reset, tipografías globales y utilidades esenciales.
   - `layout.css`: Estructura principal (`app-wrapper`, `sidebar`, `main-content`, `header`).
   - `components.css`: Botones (`btn-saas`), tarjetas (`saas-card`), tablas (`saas-table`), inputs, avatars, badges.
   - `blueprint-builder.css` / `canvas.css`: Estilos específicos de la pizarra interactiva (viewport, nodos, puertos, cables SVG).
   - `live-chat.css`: Estilos del modal de chat en vivo, burbujas de mensajería, barra fija de envío y scrollbar personalizado.
   - `floating-nav.css`: Barra flotante minimalista, tooltips y badges de retroalimentación.
2. **Importación Limpia:**
   - Se orquesta mediante `@import` en un archivo maestro centralizado o mediante inclusión explícita en las vistas Blade correspondientes.

---

### B. JavaScript (.js)
Cuando un archivo JavaScript supere las 1,000 líneas:
1. **Separación por Capas de Responsabilidad:**
   - **Módulo de Estado / Configuración:** Variables de inicialización, zoom, pan, selecciones.
   - **Módulo de Renderizado (Render Engine):** Funciones de dibujado de nodos, puertos y cables SVG Bezier.
   - **Módulo de Eventos e Interacciones:** Manejo de drag and drop, marquee selection, mousemove/mouseup, teclado y zoom rueda.
   - **Módulo de Integración de API / Red:** Peticiones HTTP `fetch` para guardar nodos, actualizar opciones y sincronizar estado con Laravel.
2. **Aislamiento Funcional:**
   - Componentes que operan en diferentes pestañas o modales (ej. Live Chat vs. Blueprint Canvas) **deben residir en scripts completamente separados** (como `live-chat.js` y `blueprint-builder.js`).

---

### C. Backend PHP (.php)
Cuando un controlador o clase PHP supere las 1,000 líneas:
1. **Controladores Delgados (Thin Controllers):**
   - Extraer lógica de negocio compleja a **Services** (ej. `app/Services/WhatsAppService.php`, `app/Services/ChatbotWorkflowService.php`).
2. **Form Requests:**
   - Mover reglas de validación extensas a clases dedicadas en `app/Http/Requests/`.
3. **Traits y Subcontroladores:**
   - Agrupar métodos cohesivos en Traits o dividir en controladores temáticos (ej. `InstanciaChatController`, `InstanciaCredentialsController`).

---

### D. Plantillas Blade (.blade.php / .html)
Cuando una vista Blade supere las 1,000 líneas:
1. **Componentes Reutilizables:**
   - Extraer bloques modulares a `resources/views/components/` o partials en subcarpetas (`@include('instancias.partials.*')`).
2. **Aislamiento de Modales:**
   - Mover cada ventana modal compleja a su propio archivo parcial dedicado (`@include('instancias.modals.chat-conversacion')`, `@include('instancias.modals.config-tecnica')`).

---

## 3. Protocolo de Verificación Obligatorio
1. Antes de finalizar cualquier cambio o entrega al usuario, verificar la longitud de los archivos modificados.
2. Si un archivo supera 1,000 líneas, ejecutar la división modular inmediatamente sin esperar a que el usuario lo solicite.
3. Mantener siempre sincronizados los directorios `resources/` y `public/`.
