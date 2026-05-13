import { onMounted, onUnmounted } from 'vue'

export function usePolling(callback: () => Promise<void> | void, interval: number) {
  let timer: ReturnType<typeof setInterval> | null = null

  const safe = () => Promise.resolve(callback()).catch(() => {})

  const start = () => {
    safe()
    timer = setInterval(safe, interval)
  }

  const stop = () => {
    if (timer !== null) {
      clearInterval(timer)
      timer = null
    }
  }

  onMounted(start)
  onUnmounted(stop)

  return { start, stop }
}
