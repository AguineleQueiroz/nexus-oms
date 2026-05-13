import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import OrderDetailView from '@/views/OrderDetailView.vue'

vi.mock('@/services/api', () => ({
  api: {
    fetchOrder:    vi.fn(),
    payOrder:      vi.fn(),
    advanceOrder:  vi.fn(),
    cancelOrder:   vi.fn(),
  },
}))

import { api } from '@/services/api'

function makeOrder(status: string) {
  return {
    id: 'ord-123',
    customer_name: 'João Silva',
    customer_email: 'joao@exemplo.com',
    total: 54990,
    status,
    items: [],
    metadata: {},
    events: [],
    created_at: '2025-01-01T00:00:00Z',
    updated_at: '2025-01-01T00:00:00Z',
  }
}

function makeRouter(id = 'ord-123') {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/orders/:id', name: 'order-detail', component: OrderDetailView },
      { path: '/', component: { template: '<div />' } },
    ],
  })
}

describe('OrderDetailView — canPay', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows Pagar button when status is payment_pending', async () => {
    vi.mocked(api.fetchOrder).mockResolvedValue(makeOrder('payment_pending') as any)
    const router = makeRouter()
    await router.push('/orders/ord-123')
    const wrapper = mount(OrderDetailView, { global: { plugins: [router] } })
    await flushPromises()
    expect(wrapper.find('button.btn-primary').exists()).toBe(true)
    expect(wrapper.find('button.btn-primary').text()).toContain('Pagar')
  })

  it('does NOT show Pagar button when status is created', async () => {
    vi.mocked(api.fetchOrder).mockResolvedValue(makeOrder('created') as any)
    const router = makeRouter()
    await router.push('/orders/ord-123')
    const wrapper = mount(OrderDetailView, { global: { plugins: [router] } })
    await flushPromises()
    expect(wrapper.find('button.btn-primary').exists()).toBe(false)
  })

  it('does NOT show Pagar button when status is paid', async () => {
    vi.mocked(api.fetchOrder).mockResolvedValue(makeOrder('paid') as any)
    const router = makeRouter()
    await router.push('/orders/ord-123')
    const wrapper = mount(OrderDetailView, { global: { plugins: [router] } })
    await flushPromises()
    expect(wrapper.find('button.btn-primary').exists()).toBe(false)
  })

  it('does NOT show Cancelar button when status is delivered', async () => {
    vi.mocked(api.fetchOrder).mockResolvedValue(makeOrder('delivered') as any)
    const router = makeRouter()
    await router.push('/orders/ord-123')
    const wrapper = mount(OrderDetailView, { global: { plugins: [router] } })
    await flushPromises()
    expect(wrapper.find('button.btn-danger').exists()).toBe(false)
  })
})
