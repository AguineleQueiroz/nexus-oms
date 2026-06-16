import {describe, expect, it} from 'vitest'
import {mount} from '@vue/test-utils'
import ConsumerGrid from '@/components/consumers/ConsumerGrid.vue'
import type {Consumer} from '@/types'

const consumers: Consumer[] = [
    {
        worker_id: 'w-1',
        worker_type: 'PaymentWorker',
        queue_name: 'orders.payment',
        status: 'active',
        last_heartbeat: new Date().toISOString(),
        events_processed: 50,
        events_failed: 2,
        started_at: '2024-01-01T09:00:00Z'
    },
    {
        worker_id: 'w-2',
        worker_type: 'AuditWorker',
        queue_name: 'orders.audit',
        status: 'idle',
        last_heartbeat: new Date().toISOString(),
        events_processed: 30,
        events_failed: 0,
        started_at: '2024-01-01T09:00:00Z'
    },
    {
        worker_id: 'w-3',
        worker_type: 'NotifyWorker',
        queue_name: 'orders.notify',
        status: 'stopped',
        last_heartbeat: new Date().toISOString(),
        events_processed: 10,
        events_failed: 1,
        started_at: '2024-01-01T09:00:00Z'
    },
]

describe('ConsumerGrid', () => {
    it('renders one card per consumer', () => {
        const wrapper = mount(ConsumerGrid, {props: {consumers}})
        const cards = wrapper.findAll('[data-testid="consumer-card"]')
        expect(cards).toHaveLength(3)
    })

    it('.pulse class is only present on active consumers', () => {
        const wrapper = mount(ConsumerGrid, {props: {consumers}})
        const pulseElements = wrapper.findAll('.pulse')
        expect(pulseElements).toHaveLength(1)
        expect(pulseElements[0].text()).not.toContain('idle')
    })

    it('shows worker_id and status on each card', () => {
        const wrapper = mount(ConsumerGrid, {props: {consumers}})
        expect(wrapper.text()).toContain('w-1')
        expect(wrapper.text()).toContain('active')
        expect(wrapper.text()).toContain('stopped')
    })

    it('sparkline is present on each card', () => {
        const wrapper = mount(ConsumerGrid, {props: {consumers}})
        const sparklines = wrapper.findAll('[data-testid="sparkline"]')
        expect(sparklines).toHaveLength(3)
    })
})
