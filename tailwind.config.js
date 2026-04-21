/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: "class",
  content: [
    "./app/views/**/*.php",
    "./app/views/**/*.html",
    "./public/**/*.php",
    "./public/**/*.html",
    "./public/scripts/**/*.js",
  ],
  theme: {
    extend: {
      fontFamily: {
        fusion: ["FusionPixel", "HarmonyOS Sans SC", "Noto Sans SC", "sans-serif"],
      },
    },
  },
};

