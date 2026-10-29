import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Thème du panneau Filament (typographie et tailles adaptées).
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
            // Pas de police distante : la pile système (New York sur Mac et
            // iPhone, Georgia ailleurs) s'affiche instantanément, sans
            // dépendance réseau ni requête vers un tiers.
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
