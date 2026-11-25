/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./includes/**/*.php",
    "./admin/*.php",
    "./admin/includes/**/*.php",
    "./src/**/*.php"
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          black: "#0F172A",
          dark: "#111827",
          blue: "#1D4ED8",
          blueHover: "#1E40AF",
          lightBlue: "#EFF6FF",
          sky: "#DBEAFE",
          red: "#DC2626",
          redHover: "#B91C1C"
        }
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', '"Inter"', 'system-ui', '-apple-system', 'sans-serif']
      }
    }
  },
  plugins: []
};
