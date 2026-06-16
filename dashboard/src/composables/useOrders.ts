import {reactive, ref, watch} from 'vue'
import {api} from '@/services/api'
import type {Order, OrdersParams} from '@/types'

export function useOrders() {
    const orders = ref<Order[]>([])
    const meta = ref({page: 1, per_page: 20, total: 0})
    const loading = ref(false)
    const filters = reactive<OrdersParams>({status: '', page: 1, per_page: 20})

    const fetch = async () => {
        loading.value = true
        try {
            const result = await api.fetchOrders(filters)
            orders.value = result.data
            meta.value = result.meta
        } finally {
            loading.value = false
        }
    }

    watch(filters, fetch, {immediate: true})

    return {orders, meta, filters, loading, fetch}
}
