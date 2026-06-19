<?php
namespace App\Services;

/**
 * Centralized Design System
 * Manages colors, typography, spacing, and design tokens
 */
class DesignSystem {
    private static $instance = null;
    
    // Color Palette
    private $colors = [
        'background' => '#f8fafc',
        'backgroundDark' => '#1e293b',
        'primary' => [
            50 => '#fff7ed',
            100 => '#ffedd5',
            200 => '#fed7aa',
            300 => '#fdba74',
            400 => '#fb923c',
            500 => '#f97316',
            600 => '#ea580c',
            700 => '#c2410c',
            800 => '#9a3412',
            900 => '#7c2d12',
        ],
        'slate' => [
            50 => '#f8fafc',
            100 => '#f1f5f9',
            200 => '#e2e8f0',
            300 => '#cbd5e1',
            400 => '#94a3b8',
            500 => '#64748b',
            600 => '#475569',
            700 => '#334155',
            800 => '#1e293b',
            900 => '#0f172a',
        ],
        'error' => [
            50 => '#fef2f2',
            100 => '#fee2e2',
            200 => '#fecaca',
            300 => '#fca5a5',
            400 => '#f87171',
            500 => '#ef4444',
            600 => '#dc2626',
            700 => '#b91c1c',
            800 => '#991b1b',
            900 => '#7f1d1d',
        ],
        'success' => [
            50 => '#f0fdf4',
            100 => '#dcfce7',
            200 => '#bbf7d0',
            300 => '#86efac',
            400 => '#4ade80',
            500 => '#22c55e',
            600 => '#16a34a',
            700 => '#15803d',
            800 => '#166534',
            900 => '#14532d',
        ],
    ];
    
    // Typography
    private $fonts = [
        'sans' => "'Plus Jakarta Sans', sans-serif",
        'mono' => "'Space Mono', monospace",
    ];
    
    // Spacing Scale
    private $spacing = [
        'xs' => '0.5rem',
        'sm' => '0.75rem',
        'md' => '1rem',
        'lg' => '1.5rem',
        'xl' => '2rem',
        '2xl' => '3rem',
        '3xl' => '4rem',
    ];
    
    // Shadow Presets
    private $shadows = [
        'soft' => '0 10px 40px -10px rgba(0,0,0,0.05)',
        'up' => '0 -10px 40px -10px rgba(0,0,0,0.08)',
        'keypad' => '0 4px 20px -2px rgba(0,0,0,0.05)',
        'xl' => '0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)',
    ];
    
    // Animation Presets
    private $animations = [
        'slideUp' => 'slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1)',
        'slideRight' => 'slideRight 0.4s cubic-bezier(0.16, 1, 0.3, 1)',
        'float' => 'float 3s ease-in-out infinite',
        'bounceSoft' => 'bounceSoft 2s ease-in-out infinite',
        'shake' => 'shake 0.4s cubic-bezier(.36,.07,.19,.97) both',
        'fadeIn' => 'fadeIn 0.3s ease-out',
    ];
    
    protected function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get color value
     */
    public function getColor($category, $shade = null) {
        if ($shade !== null) {
            return $this->colors[$category][$shade] ?? null;
        }
        return $this->colors[$category] ?? null;
    }
    
    /**
     * Get background color
     */
    public function getBackground() {
        return $this->colors['background'];
    }
    
    /**
     * Get primary color
     */
    public function getPrimary($shade = 500) {
        return $this->colors['primary'][$shade] ?? $this->colors['primary'][500];
    }
    
    /**
     * Get error color
     */
    public function getError($shade = 500) {
        return $this->colors['error'][$shade] ?? $this->colors['error'][500];
    }
    
    /**
     * Get success color
     */
    public function getSuccess($shade = 500) {
        return $this->colors['success'][$shade] ?? $this->colors['success'][500];
    }
    
    /**
     * Get font family
     */
    public function getFont($type = 'sans') {
        return $this->fonts[$type] ?? $this->fonts['sans'];
    }
    
    /**
     * Get spacing value
     */
    public function getSpacing($size) {
        return $this->spacing[$size] ?? '1rem';
    }
    
    /**
     * Get shadow value
     */
    public function getShadow($type = 'soft') {
        return $this->shadows[$type] ?? $this->shadows['soft'];
    }
    
    /**
     * Get animation value
     */
    public function getAnimation($type) {
        return $this->animations[$type] ?? null;
    }
    
    /**
     * Get Tailwind config as array
     */
    public function getTailwindConfig() {
        return [
            'theme' => [
                'extend' => [
                    'fontFamily' => [
                        'mono' => ['Space Mono', 'monospace'],
                        'sans' => ['Plus Jakarta Sans', 'sans-serif']
                    ],
                    'colors' => [
                        'primary' => $this->colors['primary'],
                    ],
                    'boxShadow' => [
                        'soft' => $this->shadows['soft'],
                        'up' => $this->shadows['up'],
                        'keypad' => $this->shadows['keypad'],
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Get Tailwind config as JavaScript
     */
    public function getTailwindConfigScript() {
        $config = $this->getTailwindConfig();
        return '<script>tailwind.config = ' . json_encode($config, JSON_PRETTY_PRINT) . ';</script>';
    }
    
    /**
     * Get CSS animations
     */
    public function getAnimationsCSS() {
        return "
        .animate-slide-up { animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes slideUp {
            0% { transform: translateY(100%); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        .animate-slide-right { animation: slideRight 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes slideRight {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(0); }
        }
        .animate-float { animation: float 3s ease-in-out infinite; }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .animate-bounce-soft { animation: bounceSoft 2s ease-in-out infinite; }
        @keyframes bounceSoft {
            0%, 100% { transform: translate(-50%, 0); }
            50% { transform: translate(-50%, -8px); }
        }
        .animate-shake { animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }
        ";
    }
    
    /**
     * Get error page classes
     */
    public function getErrorPageClasses() {
        return [
            'container' => 'min-h-screen flex items-center justify-center bg-[#f8fafc] px-4 py-12',
            'content' => 'text-center p-6',
            'title' => 'text-8xl font-black text-slate-900 mb-4',
            'heading' => 'text-3xl font-black text-slate-700 mb-4',
            'message' => 'text-lg text-slate-500 mb-8 max-w-md mx-auto',
            'buttonPrimary' => 'inline-block bg-slate-900 text-white px-8 py-4 rounded-2xl font-black hover:bg-slate-800 transition-all shadow-xl',
            'buttonSecondary' => 'inline-block bg-slate-100 text-slate-700 px-8 py-4 rounded-2xl font-black hover:bg-slate-200 transition-all',
        ];
    }
}

