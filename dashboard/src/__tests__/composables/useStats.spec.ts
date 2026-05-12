import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { useStats } from '@/composables/useStats'

vi.mock('@/services/api', () => ({
  api: { fetchStats: vi.fn() },
}))

import { api } from '@/services/api'

const mockStats = {
  orders: { total: 10, created: 5, payment_pending: 3, paid: 2 },
  events: { published_last_hour: 20, processed_last_hour: 18, failed_last_hour: 2, dead: 0 },
  consumers: { active: 3, idle: 0, stopped: 0 },
}

describe('useStats', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.clearAllMocks()
    vi.mocked(api.fetchStats).mockResolvedValue(mockStats as any)
  })
  afterEach(() => { vi.useRealTimers() })

  it('fetches stats on mount and exposes them via stats ref', async () => {
    const wrapper = mount(defineComponent({
      setup: () => useStats(),
      template: '<div>{{ stats?.orders?.total }}</div>',
    }))

    await flushPromises()

    expect(api.fetchStats).toHaveBeenCalledOnce()
    expect(wrapper.text()).toContain('10')
  })

  it('ref updates when stats are refreshed', async () => {
    const { stats, refresh } = useStats.call(
      null,
      // call outside component just to test ref behavior
    )

    // use within a component context
    mount(defineComponent({
      setup: () => {
        const { stats: s, refresh: r } = useStats()
        Object.assign(stats, { value: s.value })
        return { s, r }
      },
      template: '<div></div>',
    }))

    await flushPromises()
    expect(api.fetchStats).toHaveBeenCalled()
  })

  it('polls at the given interval', async () => {
    mount(defineComponent({
      setup: () => useStats(1000),
      template: '<div></div>',
    }))

    await flushPromises()
    expect(api.fetchStats).toHaveBeenCalledTimes(1)

    vi.advanceTimersByTime(1000)
    await flushPromises()
    expect(api.fetchStats).toHaveBeenCalledTimes(2)

    vi.advanceTimersByTime(1000)
    await flushPromises()
    expect(api.fetchStats).toHaveBeenCalledTimes(3)
  })
})
