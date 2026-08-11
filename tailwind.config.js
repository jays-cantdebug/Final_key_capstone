import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: '#1F6B3A',
                    dark: '#185828',
                    // Softened/desaturated accent used in dark mode so the
                    // brand green doesn't read as too bright against slate-900/800.
                    soft: '#4E9B71',
                },
                tint: '#EAF3EC',
                gold: {
                    DEFAULT: '#D99A2E',
                    soft: '#C69B4E',
                },
                page: '#FBFAF7',
                body: '#2C2C2A',
            },
        },
    },

    plugins: [forms],
};
