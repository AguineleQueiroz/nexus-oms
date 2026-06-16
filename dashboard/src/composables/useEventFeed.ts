import {ref} from 'vue'
import {api} from '@/services/api'
import {usePolling} from './usePolling'
import type {OrderEvent} from '@/types'

const MAX_EVENTS = 100

export function useEventFeed(interval = 2000) {
    const events = ref<OrderEvent[]>([])

    const refresh = async () => {
        try {
            const fresh = await api.fetchEventFeed(50)
            const known = new Set(events.value.map(e => e.id))
            const added = fresh.filter(e => !known.has(e.id))
            if (added.length > 0) {
                events.value = [...added, ...events.value].slice(0, MAX_EVENTS)
            }
        } catch { /* API unavailable — keep last value */
        }
    }

    usePolling(refresh, interval)

    return {events, refresh}
}
