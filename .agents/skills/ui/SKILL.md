---
name: ui
description: >-
  Use this skill for UI/UX engineering, frontend architecture, and component-based organization in Laravel.
  Enforces modular component structures in resources, separation of HTML/CSS/JS, reusable Blade components,
  and fullstack security and aesthetic design principles.
---

# UI/UX & Component Architecture Skill

This skill governs the frontend architecture, modular component structure, and UI/UX design standards for the project.

---

## 1. Core Principles

1. **Component-Based Modularity**:
   - Every domain feature in `resources/` is organized into a dedicated component folder:
     - `resources/authComponent/` (Authentication views, styles, and scripts)
     - `resources/dashboardComponent/` (Master dashboard layout, sidebar, navbar, metrics, home)
     - `resources/contactoComponent/` (Contact table, messaging modal, interaction JS)
     - `resources/instanciaComponent/` (Meta WhatsApp Cloud instances, Coexistence SDK)
     - `resources/chatbotComponent/` (Chatbot visual builder, Drawflow node editor)
2. **Separation of Concerns**:
   - HTML (Blade), CSS, and JS must never be tangled in inline blocks.
   - Each component encapsulates its Blade templates, CSS rules, and JS behaviors.
3. **Multi-Location View Resolution**:
   - Component views are registered in `config/view.php` so standard Laravel helpers (`view('auth.login')`, `view('panel-contacto')`, etc.) resolve seamlessly without breaking controller contracts.
4. **Build & Bundle Integrity**:
   - Assets are declared in `vite.config.js` and built using `pnpm run build`.
   - Production fallbacks are kept in `public/css/` and `public/js/` for instant offline and static serving.

---

## 2. Integrated Specialist References

This workspace includes specialist guidelines located in subdirectories:
- **Laravel Specialist**: [`skills/laravel-specialist/SKILL.md`](./skills/laravel-specialist/SKILL.md) (Eloquent optimization, Blade syntax, CSRF protection, route binding)
- **Fullstack Guardian**: [`skills/fullstack-guardian/SKILL.md`](./skills/fullstack-guardian/SKILL.md) (Three-perspective design: Frontend, Backend, Security; XSS prevention, client/server validation)
- **JavaScript Pro**: [`skills/javascript-pro/SKILL.md`](./skills/javascript-pro/SKILL.md) (Modern vanilla ES modules, asynchronous fetch, error handling)
- **Architecture Designer**: [`skills/architecture-designer/SKILL.md`](./skills/architecture-designer/SKILL.md) (Bounded contexts, loose coupling, high cohesion)

---

## 3. Workflow for UI Modifications

1. Identify the target component domain (`auth`, `dashboard`, `contacto`, `instancia`, `chatbot`).
2. Make modular modifications inside the corresponding `resources/<domain>Component/`.
3. Keep reusable UI primitives in `<x-...>` Blade components or includes.
4. Build assets using `pnpm run build`.
5. Verify template rendering and visual responsiveness.
