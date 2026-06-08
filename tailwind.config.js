
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './admin/views/**/*.php',
    './public/views/**/*.php',
    './assets/src/**/*.{js,css}',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Plus Jakarta Sans', 'system-ui', '-apple-system', 'sans-serif'],
      },
      colors: {
        primary: {
          DEFAULT: '#4F46E5',
          hover:   '#4338CA',
          soft:    '#EEF2FF',
          mid:     '#C7D2FE',
        },
        success: {
          DEFAULT: '#059669',
          hover:   '#047857',
          soft:    '#D1FAE5',
          mid:     '#6EE7B7',
        },
        warning: {
          DEFAULT: '#D97706',
          soft:    '#FEF3C7',
          mid:     '#FCD34D',
        },
        danger: {
          DEFAULT: '#DC2626',
          hover:   '#B91C1C',
          soft:    '#FEE2E2',
          mid:     '#FCA5A5',
        },
        info: {
          DEFAULT: '#0284C7',
          soft:    '#E0F2FE',
          mid:     '#7DD3FC',
        },
        surface: {
          DEFAULT: '#FFFFFF',
          2:       '#F8FAFC',
        },
        bg:     '#F5F7FB',
        border: {
          DEFAULT: '#E2E8F0',
          strong:  '#CBD5E1',
        },
        text: {
          DEFAULT: '#0F172A',
          muted:   '#64748B',
          faint:   '#94A3B8',
        },
      },
      borderRadius: {
        'sm':  '6px',
        'md':  '10px',
        'lg':  '14px',
        'xl':  '20px',
        '2xl': '28px',
      },
      boxShadow: {
        'xs': '0 1px 2px rgba(15,23,42,.04)',
        'sm': '0 1px 3px rgba(15,23,42,.07), 0 1px 2px rgba(15,23,42,.04)',
        'md': '0 4px 12px rgba(15,23,42,.08), 0 2px 4px rgba(15,23,42,.04)',
        'lg': '0 10px 30px rgba(15,23,42,.10), 0 4px 8px rgba(15,23,42,.05)',
        'xl': '0 20px 50px rgba(15,23,42,.14), 0 8px 16px rgba(15,23,42,.06)',
      },
      animation: {
        'spin-fast': 'spin .5s linear infinite',
        'rfid-pulse': 'rfid-pulse 2s cubic-bezier(.4,0,.6,1) infinite',
        'slide-up': 'slideUp .2s cubic-bezier(.16,1,.3,1)',
        'slide-down': 'slideDown .25s cubic-bezier(.16,1,.3,1)',
        'bounce-in': 'bounce-in .3s ease',
        'fade-in': 'fadeIn .15s ease',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
};
