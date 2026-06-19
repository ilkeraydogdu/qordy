<script>
  // AssetManager compiled tailwind.min.css modunda runtime JS yüklenmiyor; bu
  // konfigürasyonu sadece Play CDN (JIT) aktifken uygula, aksi halde
  // ReferenceError atar.
  if (typeof tailwind !== 'undefined' && tailwind && typeof tailwind === 'object') {
  tailwind.config = {
    theme: {
      screens: {
        'xs': '475px',
        'sm': '640px',
        'md': '768px',
        'lg': '1024px',
        'xl': '1280px',
        '2xl': '1536px',
      },
      extend: {
        fontFamily: { 
          mono: ['Space Mono', 'monospace'],
          sans: ['Plus Jakarta Sans', 'sans-serif']
        },
        colors: {
          primary: { 
            50: '#fff7ed', 
            100: '#ffedd5', 
            200: '#fed7aa', 
            300: '#fdba74', 
            400: '#fb923c', 
            500: '#f97316', 
            600: '#ea580c', 
            700: '#c2410c', 
            800: '#9a3412', 
            900: '#7c2d12' 
          }
        },
        fontSize: {
          'responsive-xs': ['0.625rem', { lineHeight: '1rem' }],      // 10px mobile, 12px tablet+
          'responsive-sm': ['0.75rem', { lineHeight: '1.25rem' }],     // 12px mobile, 14px tablet+
          'responsive-base': ['0.875rem', { lineHeight: '1.5rem' }],  // 14px mobile, 16px tablet+
          'responsive-lg': ['1rem', { lineHeight: '1.75rem' }],        // 16px mobile, 18px tablet+, 20px desktop+
          'responsive-xl': ['1.125rem', { lineHeight: '2rem' }],      // 18px mobile, 20px tablet+, 24px desktop+
          'responsive-2xl': ['1.25rem', { lineHeight: '2rem' }],       // 20px mobile, 24px tablet+, 30px desktop+
          'responsive-3xl': ['1.5rem', { lineHeight: '2.25rem' }],    // 24px mobile, 30px tablet+, 36px desktop+
        },
        spacing: {
          'responsive-xs': '0.5rem',   // 8px mobile, 12px tablet+, 16px desktop+
          'responsive-sm': '0.75rem',  // 12px mobile, 16px tablet+, 24px desktop+
          'responsive-md': '1rem',     // 16px mobile, 24px tablet+, 32px desktop+
          'responsive-lg': '1.5rem',   // 24px mobile, 32px tablet+, 40px desktop+
        },
        borderRadius: {
          'responsive-sm': '0.75rem',   // 12px mobile, 16px tablet+, 20px desktop+
          'responsive-md': '1rem',      // 16px mobile, 24px tablet+, 32px desktop+
          'responsive-lg': '1.25rem',   // 20px mobile, 28px tablet+, 40px desktop+
        },
        boxShadow: { 
          'soft': '0 10px 40px -10px rgba(0,0,0,0.05)', 
          'up': '0 -10px 40px -10px rgba(0,0,0,0.08)', 
          'keypad': '0 4px 20px -2px rgba(0,0,0,0.05)' 
        },
        maxWidth: {
          'container': '1280px',
          'container-sm': '640px',
          'container-md': '768px',
          'container-lg': '1024px',
        }
      }
    }
  };
  }
</script>

