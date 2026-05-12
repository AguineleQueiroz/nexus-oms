import { ref } from 'vue'
import { api } from '@/services/api'
import { usePolling } from './usePolling'
import type { Consumer } from '@/types'

export function useConsumers(interval = 5000) {
  const consumers = ref<Consumer[]>([])

  const refresh = async () => {
    consumers.value = await api.fetchConsumers()
  }

  usePolling(refresh, interval)

  return { consumers, refresh }
}
