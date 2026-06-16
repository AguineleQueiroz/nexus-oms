import {describe, expect, it} from 'vitest'
import {mount} from '@vue/test-utils'
import OrderPipeline from '@/components/orders/OrderPipeline.vue'

describe('OrderPipeline', () => {
    it('renders a node for each pipeline step', () => {
        const wrapper = mount(OrderPipeline, {props: {currentStatus: 'paid'}})
        expect(wrapper.find('[data-testid="order-pipeline"]').exists()).toBe(true)
        expect(wrapper.findAll('.state-node').length).toBeGreaterThanOrEqual(6)
    })

    it('marks current status node as active', () => {
        const wrapper = mount(OrderPipeline, {props: {currentStatus: 'paid'}})
        const activeNode = wrapper.find('.state-node.active')
        expect(activeNode.exists()).toBe(true)
        expect(activeNode.attributes('data-status')).toBe('paid')
    })

    it('marks preceding nodes as completed', () => {
        const wrapper = mount(OrderPipeline, {props: {currentStatus: 'picking'}})
        const completed = wrapper.findAll('.state-node.completed')
        expect(completed.length).toBeGreaterThanOrEqual(3) // created, payment_pending, paid
    })

    it('marks subsequent nodes as pending', () => {
        const wrapper = mount(OrderPipeline, {props: {currentStatus: 'created'}})
        const pending = wrapper.findAll('.state-node.pending')
        expect(pending.length).toBeGreaterThanOrEqual(4)
    })
})
