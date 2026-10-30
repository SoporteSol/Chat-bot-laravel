import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/auth.css',
                'resources/css/dashboard.css',
                'resources/css/blueprint-builder.css',
                'resources/css/crm-suite.css',
                'resources/css/meta-onboarding.css',
                'resources/js/app.js',
                'resources/js/auth.js',
                'resources/js/dashboard.js',
                'resources/js/instancias.js',
                'resources/js/meta-onboarding.js',
                'resources/js/floating-navbar.js',
                'resources/js/prompt-simulator.js',
                'resources/js/blueprint-drawer.js',
                'resources/js/blueprint-builder.js',
                'resources/js/live-chat.js',
                'resources/js/crm-suite.js',
            ],
            refresh: true,
        }),
    ],
});
