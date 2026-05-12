import { createApp } from 'vue'
import router from './router'
import App from './App.vue'
import './assets/tokens.css'

createApp(App).use(router).mount('#app')
