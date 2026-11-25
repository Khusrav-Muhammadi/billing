import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * Tailwind только для новой админки (layouts.admin и 16 разделов).
 * Старые страницы на layouts.app этот CSS не подключают.
 * collapse и container заняты Bootstrap — их Tailwind-версии ломают модалки и сетку.
 */
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/tailwind.blade.php',
        './resources/views/components/**/*.blade.php',
        './resources/views/layouts/admin.blade.php',
        './resources/views/dashboard.blade.php',
        './resources/views/admin/layout/**/*.blade.php',
        './resources/views/admin/health/**/*.blade.php',
        './resources/views/admin/partials/**/*.blade.php',
        './resources/views/admin/clients/create.blade.php',
        './resources/views/v2/sub_domains/**/*.blade.php',
        './resources/views/v2/organizations_v2/**/*.blade.php',
        './resources/views/admin/ai-subscriptions/**/*.blade.php',
        './resources/views/admin/site-applications/**/*.blade.php',
        './resources/views/admin/partners/**/*.blade.php',
        './resources/views/admin/applications/**/*.blade.php',
        './resources/views/admin/tariffs/**/*.blade.php',
        './resources/views/admin/accounts/**/*.blade.php',
        './resources/views/admin/currency_rates/**/*.blade.php',
        './resources/views/admin/implementation/**/*.blade.php',
        './resources/views/admin/day_closings/**/*.blade.php',
        './resources/views/admin/ai-models/**/*.blade.php',
        './resources/views/admin/ai-tariffs/**/*.blade.php',
        './resources/js/admin.js',
    ],
    blocklist: ['collapse', 'container'],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Onest', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#eef0ff', 100: '#e0e3ff', 200: '#c5caff', 300: '#9ba3ff', 400: '#6870ff',
                    500: '#3a40fe', 600: '#0b0efd', 700: '#080bd0', 800: '#0a0da6', 900: '#0e1183', 950: '#08094d',
                },
                ink: {
                    50: '#f8f9fb', 100: '#f3f4f6', 200: '#e7e8eb', 300: '#cfd2da', 400: '#9a9fb2',
                    500: '#6b7088', 600: '#4b5068', 700: '#383c57', 800: '#272b46', 900: '#1c1f36', 950: '#12142a',
                },
                gold: { 50: '#fffbeb', 100: '#fef3c7', 400: '#fbbf24', 500: '#f59e0b', 600: '#d97706' },
            },
            maxWidth: { content: '1200px' },
            borderRadius: { card: '0.75rem', panel: '1.25rem' },
            boxShadow: {
                card: '0 1px 2px rgba(17,24,38,.04), 0 8px 24px -12px rgba(17,24,38,.12)',
            },
        },
    },
    plugins: [forms({ strategy: 'class' })],
};
