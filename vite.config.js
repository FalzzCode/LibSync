import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // The application uses its intentionally layered public CSS
            // theme. Only the interactive JS entry is consumed by Blade;
            // leaving the unused starter Tailwind entry out keeps deploy
            // artifacts smaller and avoids shipping duplicate resets.
            input: ['resources/js/app.js'],
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
