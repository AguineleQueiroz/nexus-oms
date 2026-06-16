import type {Config} from 'tailwindcss'

export default {
    content: [
        './index.html',
        './src/**/*.{vue,ts,tsx}',
    ],
    theme: {
        extend: {
            colors: {
                'bg-base': '#0A0A0B',
                'bg-surface': '#111113',
                'bg-elevated': '#1A1A1D',
                'border-dim': '#2A2A2E',
                'accent': '#F59E0B',
                'accent-dim': '#78450A',
                'text-primary': '#F4F4F5',
                'text-muted': '#71717A',
                'text-dim': '#3F3F46',
            },
            fontFamily: {
                display: ['"DM Serif Display"', 'serif'],
                mono: ['"JetBrains Mono"', 'monospace'],
                sans: ['"DM Sans"', 'sans-serif'],
            },
        },
    },
    plugins: [],
} satisfies Config
