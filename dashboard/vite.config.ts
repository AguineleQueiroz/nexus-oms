import {defineConfig} from 'vite'
import vue from '@vitejs/plugin-vue'
import {resolve} from 'path'

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'src'),
        },
    },
    server: {
        host: '0.0.0.0',
        watch: {
            usePolling: true,
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./src/__tests__/setup.ts'],
    },
})
