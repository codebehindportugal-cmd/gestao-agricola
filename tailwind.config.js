import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Paleta do Gestão Agrícola: um verde só para ações e estado "bom",
// neutros quentes para o resto. emerald/slate/gray foram redefinidos para que
// todas as páginas antigas herdem a paleta sem mudar classe a classe.
const verde = {
    50: '#F0F6F2',
    100: '#E6F0E9',
    200: '#C9DDD0',
    300: '#A2C4AE',
    400: '#6E9E83',
    500: '#3E7D5E',
    600: '#2F6B4F',
    700: '#1F4D38',
    800: '#163A2A',
    900: '#0F2A1E',
    950: '#08180F',
};

const neutro = {
    50: '#FAF9F6',
    100: '#F2F1EC',
    200: '#E2DFD6',
    300: '#D9D6CC',
    400: '#767A72',
    500: '#6B6F68',
    600: '#5A5F58',
    700: '#3D453F',
    800: '#2A322D',
    900: '#1C2420',
    950: '#121815',
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                verde,
                emerald: verde,
                green: verde,
                slate: neutro,
                gray: neutro,
                neutro,
                fundo: '#F5F4EF',
                indigo: verde,
                // ocre para fertilização / avisos suaves
                ocre: {
                    50: '#FCF6EA',
                    100: '#FBF1DE',
                    200: '#EBD3A6',
                    500: '#B9822A',
                    700: '#7A4A1F',
                    900: '#4A2E10',
                },
            },
        },
    },

    plugins: [forms],
};
