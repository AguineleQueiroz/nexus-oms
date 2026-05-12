import { onMounted, onUnmounted } from 'vue'

export function usePolling(callback: () => void, interval: number) {
  let timer: ReturnType<typeof setInterval> | null = null

  const start = () => {
    callback()
    timer = setInterval(callback, interval)
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
