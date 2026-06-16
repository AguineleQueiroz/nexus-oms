import {ref} from 'vue'
import {api} from '@/services/api'
import {usePolling} from './usePolling'
import type {Consumer} from '@/types'

export function useConsumers(interval = 5000) {
    const consumers = ref<Consumer[]>([])

    const refresh = async () => {
        try {
            consumers.value = await api.fetchConsumers()
        } catch { /* API unavailable — keep last value */
        }
    }

    usePolling(refresh, interval)

    return {consumers, refresh}
}
