/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: "class",
  content: [
    "./app/views/**/*.php",
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

