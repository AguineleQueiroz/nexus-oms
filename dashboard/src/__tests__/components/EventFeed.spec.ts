import {describe, expect, it} from 'vitest'
import {mount} from '@vue/test-utils'
import EventFeed from '@/components/EventFeed.vue'
import type {OrderEvent} from '@/types'

function makeEvent(id: string, routing_key: string, published_at: string): OrderEvent {
    return {
        id,
        order_id: 'ord-1',
        event_type: 'order.created',
        routing_key,
        payload: {},
        attempt: 1,
        processed: false,
        published_at,
    }
}

const events: OrderEvent[] = [
    makeEvent('evt-3', 'order.paid', '2024-01-01T10:03:00Z'),
    makeEvent('evt-2', 'order.payment_pending', '2024-01-01T10:02:00Z'),
    makeEvent('evt-1', 'order.created', '2024-01-01T10:01:00Z'),
]

describe('EventFeed', () => {
    it('renders one row per event', () => {
        const wrapper = mount(EventFeed, {props: {events}})
        const rows = wrapper.findAll('[data-testid="event-row"]')
        expect(rows).toHaveLength(3)
    })

    it('first item in DOM is the most recent event (top)', () => {
        const wrapper = mount(EventFeed, {props: {events}})
        const rows = wrapper.findAll('[data-testid="event-row"]')
        expect(rows[0].text()).toContain('evt-3')
    })

    it('routing_key is rendered in monospace element', () => {
        const wrapper = mount(EventFeed, {props: {events}})
        const mono = wrapper.find('code')
        expect(mono.exists()).toBe(true)
        expect(mono.text()).toContain('order.paid')
    })

    it('renders empty state when no events', () => {
        const wrapper = mount(EventFeed, {props: {events: []}})
        expect(wrapper.find('[data-testid="event-empty"]').exists()).toBe(true)
    })
})
