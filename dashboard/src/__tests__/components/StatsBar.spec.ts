import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import StatsBar from '@/components/StatsBar.vue'
import type { Stats } from '@/types'

const mockStats: Stats = {
  orders: { total: 42, created: 10, payment_pending: 8, paid: 24 },
  events: { published_last_hour: 100, processed_last_hour: 95, failed_last_hour: 5, dead: 2 },
  consumers: { active: 3, idle: 1, stopped: 0 },
}

describe('StatsBar', () => {
  it('renders 4 cards with correct labels', () => {
    const wrapper = mount(StatsBar, { props: { stats: mockStats } })
    const cards = wrapper.findAll('[data-testid="stat-card"]')
    expect(cards).toHaveLength(4)
  })

  it('shows total orders count', () => {
    const wrapper = mount(StatsBar, { props: { stats: mockStats } })
    expect(wrapper.text()).toContain('42')
  })

  it('shows events published last hour', () => {
    const wrapper = mount(StatsBar, { props: { stats: mockStats } })
    expect(wrapper.text()).toContain('100')
  })

  it('shows active consumers count', () => {
    const wrapper = mount(StatsBar, { props: { stats: mockStats } })
    expect(wrapper.text()).toContain('3')
  })

  it('updates numbers when stats prop changes', async () => {
    const wrapper = mount(StatsBar, { props: { stats: mockStats } })
    expect(wrapper.text()).toContain('42')

    await wrapper.setProps({
      stats: { ...mockStats, orders: { ...mockStats.orders, total: 99 } },
    })
    expect(wrapper.text()).toContain('99')
  })
})
