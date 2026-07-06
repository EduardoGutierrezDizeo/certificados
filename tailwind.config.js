import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                serif: ['"Source Serif 4"', ...defaultTheme.fontFamily.serif],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                ink: {
                    DEFAULT: '#16324F', 50: '#EAF0F5', 100: '#D5E1EB',
                    600: '#1D3F63', 700: '#16324F', 800: '#102538', 900: '#0B1A26',
                },
                brass: {
                    DEFAULT: '#B08D57', 50: '#F7F1E7', 100: '#EFE3CF',
                    400: '#C4A16F', 500: '#B08D57', 600: '#8F7143',
                },
                surface: '#F7F8FA',
                carbon: '#1F2429',
                rust: '#B54B3F',
            },
        },
    },
    plugins: [forms],
};
