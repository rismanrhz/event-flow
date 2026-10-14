/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./index.html', './src/**/*.{vue,ts}'],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eef2ff',
          100: '#e0e7ff',
          200: '#c7d2fe',
          300: '#a5b4fc',
          400: '#818cf8',
          500: '#6366f1',
          600: '#4f46e5',
          700: '#4338ca',
          800: '#3730a3',
          900: '#312e81',
          950: '#1e1b4b',
        },
        secondary: {
          50: '#f0fdfa',
          100: '#ccfbf1',
          200: '#99f6e4',
          300: '#5eead4',
          400: '#2dd4bf',
          500: '#14b8a6',
          600: '#0d9488',
          700: '#0f766e',
          800: '#115e59',
          900: '#134e4a',
        },
        accent: {
          50: '#fffbeb',
          100: '#fef3c7',
          200: '#fde68a',
          300: '#fcd34d',
          400: '#fbbf24',
          500: '#f59e0b',
          600: '#d97706',
          700: '#b45309',
        },
        surface: {
          DEFAULT: '#ffffff',
          muted: '#f8fafc',
        },
        ink: {
          DEFAULT: '#0f172a',
          soft: '#334155',
          faint: '#64748b',
        },
      },
      fontFamily: {
        sans: [
          'Inter',
          'ui-sans-serif',
          'system-ui',
          '-apple-system',
          'Segoe UI',
          'Roboto',
          'Helvetica Neue',
          'Arial',
          'sans-serif',
        ],
      },
      boxShadow: {
        card: '0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.06)',
        cardHover: '0 8px 24px -8px rgb(15 23 42 / 0.14)',
        glow: '0 0 0 1px rgb(79 70 229 / 0.08), 0 8px 28px -6px rgb(79 70 229 / 0.35)',
        sidebar: '0 0 0 9999px rgb(15 23 42 / 0.5)',
      },
      backgroundImage: {
        'hero-gradient':
          'radial-gradient(1200px 480px at 50% -12%, rgb(99 102 241 / 0.16), transparent 60%), linear-gradient(180deg, #ffffff 0%, #f8fafc 100%)',
        'panel-gradient':
          'radial-gradient(720px 320px at 100% 0%, rgb(230 245 242 / 0.9), transparent 55%), linear-gradient(180deg, #ffffff 0%, #f8fafc 100%)',
      },
      animation: {
        'fade-in': 'fadeIn 0.4s ease-out both',
        'rise-in': 'riseIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) both',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        riseIn: {
          '0%': { opacity: '0', transform: 'translateY(8px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
    },
  },
  plugins: [],
}