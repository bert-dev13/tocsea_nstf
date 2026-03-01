import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/landing.css', 'resources/js/landing.js', 'resources/css/login.css', 'resources/js/login.js', 'resources/views/user/css/dashboard.css', 'resources/views/user/js/dashboard.js', 'resources/views/user/css/settings.css', 'resources/views/user/js/settings.js', 'resources/views/user/css/soil-calculator.css', 'resources/views/user/js/soil-calculator.js', 'resources/views/user/css/model-builder.css', 'resources/views/user/js/model-builder.js', 'resources/views/user/js/saved-equations.js', 'resources/views/user/css/calculation-history.css', 'resources/views/user/css/saved-equations.css', 'resources/views/user/js/calculation-history.js', 'resources/views/user/css/ask-tocsea.css', 'resources/views/user/js/ask-tocsea.js', 'resources/views/admin/css/dashboard.css', 'resources/views/admin/js/dashboard.js', 'resources/views/admin/css/users.css', 'resources/views/admin/js/users.js', 'resources/views/admin/css/models.css', 'resources/views/admin/js/models.js', 'resources/views/admin/css/settings.css', 'resources/views/admin/js/settings.js', 'resources/views/admin/css/calculations.css', 'resources/views/admin/js/calculations.js', 'resources/views/admin/css/analytics.css', 'resources/views/admin/css/pagination.css', 'resources/views/admin/js/pagination.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
