import {beforeEach, describe, expect, it, vi} from 'vitest'
import {flushPromises, mount} from '@vue/test-utils'
import {createMemoryHistory, createRouter} from 'vue-router'
import OrdersView from '@/views/OrdersView.vue'
import {api} from '@/services/api'

vi.mock('@/services/api', () => ({
    api: {fetchOrders: vi.fn()},
}))

const makeResponse = (overrides: object = {}) => ({
    data: [
        {
            id: 'ord-1',
            customer_name: 'Ana',
            status: 'created',
            total: 100,
            customer_email: '',
            items: [],
            metadata: {},
            created_at: '',
            updated_at: ''
        },
        {
            id: 'ord-2',
            customer_name: 'Bob',
            status: 'paid',
            total: 200,
            customer_email: '',
            items: [],
            metadata: {},
            created_at: '',
            updated_at: ''
        },
    ],
    meta: {page: 1, per_page: 20, total: 2},
    ...overrides,
})

function makeRouter() {
    return createRouter({
        history: createMemoryHistory(),
        routes: [
            {path: '/', component: {template: '<div />'}},
            {path: '/orders', component: OrdersView},
            {path: '/orders/:id', name: 'order-detail', component: {template: '<div />'}},
        ],
    })
}

describe('OrdersView', () => {
    beforeEach(() => {
        vi.mocked(api.fetchOrders).mockResolvedValue(makeResponse() as any)
    })

    it('renders a table with orders', async () => {
        const router = makeRouter()
        await router.push('/orders')

        const wrapper = mount(OrdersView, {
            global: {plugins: [router]},
        })

        await flushPromises()

        const rows = wrapper.findAll('[data-testid="order-row"]')
        expect(rows).toHaveLength(2)
    })

    it('clicking a row navigates to order detail', async () => {
        const router = makeRouter()
        await router.push('/orders')

        const wrapper = mount(OrdersView, {
            global: {plugins: [router]},
        })

        await flushPromises()

        const firstRow = wrapper.find('[data-testid="order-row"]')
        await firstRow.trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe('/orders/ord-1')
    })

    it('changing status filter triggers a new fetch', async () => {
        const router = makeRouter()
        await router.push('/orders')

        const wrapper = mount(OrdersView, {
            global: {plugins: [router]},
        })

        await flushPromises()

        const select = wrapper.find('[data-testid="status-filter"]')
        await select.setValue('paid')
        await flushPromises()

        const calls = vi.mocked(api.fetchOrders).mock.calls
        const lastCall = calls.at(-1)![0]
        expect(lastCall.status).toBe('paid')
    })
})
