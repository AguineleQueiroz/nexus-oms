import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FunnelChart from '@/components/charts/FunnelChart.vue'
import type { FunnelItem } from '@/types'

const funnel: FunnelItem[] = [
  { status: 'created',         count: 100 },
  { status: 'payment_pending', count: 80 },
  { status: 'paid',            count: 60 },
  { status: 'preparing',       count: 50 },
  { status: 'ready',           count: 40 },
  { status: 'in_transit',      count: 30 },
  { status: 'delivered',       count: 20 },
  { status: 'cancelled',       count: 5  },
]

describe('FunnelChart', () => {
  it('renders 8 bars', () => {
    const wrapper = mount(FunnelChart, { props: { data: funnel } })
    const bars = wrapper.findAll('[data-testid="funnel-bar"]')
    expect(bars).toHaveLength(8)
  })

  it('bar for "entregue" (delivered) is wider than "cancelado" (cancelled)', () => {
    const wrapper = mount(FunnelChart, { props: { data: funnel } })
    const bars = wrapper.findAll('[data-testid="funnel-bar"]')
    const deliveredBar = bars.find(b => b.attributes('data-status') === 'delivered')
    const cancelledBar = bars.find(b => b.attributes('data-status') === 'cancelled')
    expect(deliveredBar).toBeDefined()
    expect(cancelledBar).toBeDefined()

    const deliveredWidth = parseFloat(deliveredBar!.attributes('style')?.match(/width:\s*([\d.]+)/)?.[1] ?? '0')
    const cancelledWidth = parseFloat(cancelledBar!.attributes('style')?.match(/width:\s*([\d.]+)/)?.[1] ?? '0')
    expect(deliveredWidth).toBeGreaterThan(cancelledWidth)
  })

  it('renders data-status attribute for each bar matching the item status', () => {
    const wrapper = mount(FunnelChart, { props: { data: funnel } })
    funnel.forEach(item => {
      const bar = wrapper.find(`[data-status="${item.status}"]`)
      expect(bar.exists()).toBe(true)
    })
  })
})
