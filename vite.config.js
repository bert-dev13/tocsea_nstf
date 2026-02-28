import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/landing.css', 'resources/js/landing.js', 'resources/css/login.css', 'resources/js/login.js', 'resources/css/dashboard.css', 'resources/js/dashboard.js', 'resources/css/settings.css', 'resources/js/settings.js', 'resources/css/soil-calculator.css', 'resources/js/soil-calculator.js', 'resources/css/model-builder.css', 'resources/js/model-builder.js', 'resources/js/saved-equations.js', 'resources/css/calculation-history.css', 'resources/css/saved-equations.css', 'resources/js/calculation-history.js', 'resources/css/ask-tocsea.css', 'resources/js/ask-tocsea.js'],
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
