import {describe, expect, it} from 'vitest'
import {flushPromises, mount} from '@vue/test-utils'
import EventFlowChart from '@/components/charts/EventFlowChart.vue'
import type {OrderEvent} from '@/types'

const mockEvents: OrderEvent[] = [
    {
        id: 'e1',
        order_id: 'o1',
        event_type: 'order.created',
        routing_key: 'order.created',
        payload: {},
        attempt: 1,
        processed: true,
        published_at: '2025-01-01T10:00:00Z'
    },
    {
        id: 'e2',
        order_id: 'o1',
        event_type: 'order.payment.pending',
        routing_key: 'order.payment.pending',
        payload: {},
        attempt: 1,
        processed: true,
        published_at: '2025-01-01T10:00:01Z'
    },
    {
        id: 'e3',
        order_id: 'o2',
        event_type: 'order.created',
        routing_key: 'order.created',
        payload: {},
        attempt: 1,
        processed: true,
        published_at: '2025-01-01T10:01:00Z'
    },
    {
        id: 'e4',
        order_id: 'o2',
        event_type: 'order.payment.approved',
        routing_key: 'order.payment.approved',
        payload: {},
        attempt: 1,
        processed: true,
        published_at: '2025-01-01T10:01:02Z'
    },
]

describe('EventFlowChart', () => {
    it('renders an SVG when events are provided', async () => {
        const wrapper = mount(EventFlowChart, {props: {events: mockEvents}})
        await flushPromises()
        expect(wrapper.find('[data-testid="event-flow-svg"]').exists()).toBe(true)
    })

    it('shows empty placeholder when no events', () => {
        const wrapper = mount(EventFlowChart, {props: {events: []}})
        expect(wrapper.find('[data-testid="chart-empty"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="event-flow-svg"]').exists()).toBe(false)
    })
})
