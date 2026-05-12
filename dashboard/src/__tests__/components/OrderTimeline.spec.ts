import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import OrderTimeline from '@/components/orders/OrderTimeline.vue'
import type { OrderEvent } from '@/types'

const events: OrderEvent[] = [
  { id: 'e1', order_id: 'o1', event_type: 'order.created',         routing_key: 'order.created',         payload: { source: 'web' }, attempt: 1, processed: true,  published_at: '2024-01-01T10:00:00Z', processed_at: '2024-01-01T10:00:01Z' },
  { id: 'e2', order_id: 'o1', event_type: 'order.payment_pending', routing_key: 'order.payment_pending', payload: { method: 'pix' }, attempt: 1, processed: true,  published_at: '2024-01-01T10:01:00Z', processed_at: '2024-01-01T10:01:02Z' },
  { id: 'e3', order_id: 'o1', event_type: 'order.paid',            routing_key: 'order.paid',            payload: { amount: 100 },  attempt: 1, processed: false, published_at: '2024-01-01T10:02:00Z' },
]

describe('OrderTimeline', () => {
  it('renders one entry per event', () => {
    const wrapper = mount(OrderTimeline, { props: { events } })
    const entries = wrapper.findAll('[data-testid="timeline-entry"]')
    expect(entries).toHaveLength(3)
  })

  it('events are in chronological order (oldest first)', () => {
    const wrapper = mount(OrderTimeline, { props: { events } })
    const entries = wrapper.findAll('[data-testid="timeline-entry"]')
    expect(entries[0].text()).toContain('order.created')
    expect(entries[2].text()).toContain('order.paid')
  })

  it('shows payload as JSON in tooltip or details', () => {
    const wrapper = mount(OrderTimeline, { props: { events } })
    expect(wrapper.html()).toContain('"source"')
    expect(wrapper.html()).toContain('"web"')
  })
})
