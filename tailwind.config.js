/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./includes/**/*.php"],
  theme: {
    extend: {
      colors: {
        'searchjet-blue': '#3b4cb8',
        'searchjet-blue-light': '#4c63d2',
      }
    },
  },
  plugins: [],
}