import {ref} from 'vue'
import {api} from '@/services/api'
import {usePolling} from './usePolling'
import type {Stats} from '@/types'

export function useStats(interval = 5000) {
    const stats = ref<Stats | null>(null)

    const refresh = async () => {
        try {
            stats.value = await api.fetchStats()
        } catch { /* API unavailable — keep last value */
        }
    }

    usePolling(refresh, interval)

    return {stats, refresh}
}
