import {createRouter, createWebHistory} from 'vue-router'
import DashboardView from '@/views/DashboardView.vue'
import OrdersView from '@/views/OrdersView.vue'
import OrderDetailView from '@/views/OrderDetailView.vue'
import ConsumersView from '@/views/ConsumersView.vue'
import EventsView from '@/views/EventsView.vue'
import NotificationsView from '@/views/NotificationsView.vue'

export default createRouter({
    history: createWebHistory(),
    routes: [
        {path: '/', name: 'dashboard', component: DashboardView},
        {path: '/orders', name: 'orders', component: OrdersView},
        {path: '/orders/:id', name: 'order-detail', component: OrderDetailView},
        {path: '/consumers', name: 'consumers', component: ConsumersView},
        {path: '/events', name: 'events', component: EventsView},
        {path: '/notifications', name: 'notifications', component: NotificationsView},
    ],
})
