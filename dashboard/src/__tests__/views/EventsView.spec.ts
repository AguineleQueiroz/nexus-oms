import {beforeEach, describe, expect, it, vi} from 'vitest'
import {flushPromises, mount} from '@vue/test-utils'
import EventsView from '@/views/EventsView.vue'
import {api} from '@/services/api'

vi.mock('@/services/api', () => ({
    api: {
        fetchEventFeed: vi.fn(),
        fetchEventsByType: vi.fn(),
    },
}))

const mockEvents = [
    {
        id: 'evt-1', order_id: 'ord-aaa-111', event_type: 'order.created',
        routing_key: 'order.created', payload: {customer_name: 'Ana', total: 100},
        worker_id: 'audit-worker-1', attempt: 1, processed: true,
        published_at: '2025-01-01T10:00:00Z', processed_at: '2025-01-01T10:00:01Z',
    },
    {
        id: 'evt-2', order_id: 'ord-bbb-222', event_type: 'order.payment.approved',
        routing_key: 'order.payment.approved', payload: {customer_name: 'Bob', total: 200},
        worker_id: 'payment-worker-1', attempt: 1, processed: true,
        published_at: '2025-01-01T10:01:00Z', processed_at: '2025-01-01T10:01:02Z',
    },
    {
        id: 'evt-3', order_id: 'ord-ccc-333', event_type: 'order.cancelled',
        routing_key: 'order.cancelled', payload: {customer_name: 'Carla', total: 50},
        worker_id: 'audit-worker-1', attempt: 1, processed: false,
        published_at: '2025-01-01T10:02:00Z', processed_at: null,
    },
]

const mockEventTypes = [
    {event_type: 'order.created', count: 1},
    {event_type: 'order.payment.approved', count: 1},
    {event_type: 'order.cancelled', count: 1},
]

describe('EventsView', () => {
    beforeEach(() => {
        vi.mocked(api.fetchEventFeed).mockResolvedValue(mockEvents as any)
        vi.mocked(api.fetchEventsByType).mockResolvedValue(mockEventTypes as any)
    })

    it('renders a table row for each event', async () => {
        const wrapper = mount(EventsView, {global: {stubs: {Teleport: true}}})
        await flushPromises()
        const rows = wrapper.findAll('[data-testid="event-row"]')
        expect(rows).toHaveLength(3)
    })

    it('shows all events when no filter is selected', async () => {
        const wrapper = mount(EventsView, {global: {stubs: {Teleport: true}}})
        await flushPromises()
        expect(wrapper.findAll('[data-testid="event-row"]')).toHaveLength(3)
    })

    it('filters rows by event type when filter is changed', async () => {
        const wrapper = mount(EventsView, {global: {stubs: {Teleport: true}}})
        await flushPromises()

        const select = wrapper.find('[data-testid="type-filter"]')
        await select.setValue('order.cancelled')
        await flushPromises()

        expect(wrapper.findAll('[data-testid="event-row"]')).toHaveLength(1)
    })

    it('opens payload modal when clicking { … } button', async () => {
        const wrapper = mount(EventsView, {global: {stubs: {Teleport: true}}})
        await flushPromises()

        const btn = wrapper.find('[data-testid="open-payload"]')
        await btn.trigger('click')

        expect(wrapper.find('[data-testid="payload-modal"]').exists()).toBe(true)
    })
})
