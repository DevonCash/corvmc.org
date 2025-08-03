/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      fontFamily: {
        'sans': ['Lexend', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      colors: {
        'corvmc-orange': '#e5771e',
        'corvmc-yellow': '#ffe28a', 
        'corvmc-blue': '#003b5c',
        'corvmc-light-blue': '#B8DDE1',
      }
    },
  },
  plugins: [
    require('daisyui'),
  ],
}