/** Local production styles for the public homepage and API documentation. */
module.exports = {
  content: ['./index.html', './docs.html', './portal.html'],
  darkMode: 'class',
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
        bengali: ['"Anek Bangla"', '"Hind Siliguri"', 'sans-serif'],
        heading: ['Outfit', '"Anek Bangla"', 'sans-serif'],
        mono: ['"JetBrains Mono"', '"Fira Code"', 'monospace'],
      },
      colors: {
        gold: {
          50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d',
          400: '#fbbf24', 500: '#f59e0b', 600: '#d97706', 700: '#b45309',
          800: '#92400e', 900: '#78350f', 950: '#451a03',
        },
        khata: {
          paper: '#FFFBF5',
          cream: '#FFF8EC',
          line: '#F1E8D9',
          red: '#E5484D',
          darkRed: '#C52B30',
        },
        river: {
          night: '#0B132B',
          deep: '#060B18',
          card: '#111B35',
          border: '#1E2C52',
          glow: '#2DD4BF',
          teal: '#0F766E',
        },
        brand: {
          gold: '#c9933b',
          goldDark: '#ca8a04',
          darkBg: '#0B132B',
          cardBg: '#111B35'
        },
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms')({ strategy: 'class' }),
    require('@tailwindcss/typography'),
    require('@tailwindcss/aspect-ratio'),
  ],
};
