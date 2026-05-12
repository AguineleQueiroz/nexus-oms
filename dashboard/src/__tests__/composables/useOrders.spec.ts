import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { useOrders } from '@/composables/useOrders'

vi.mock('@/services/api', () => ({
  api: { fetchOrders: vi.fn() },
}))

import { api } from '@/services/api'

const makeResponse = (status?: string) => ({
  data: [
    { id: 'ord-1', customer_name: 'Ana', status: status ?? 'created', total: 100 },
    { id: 'ord-2', customer_name: 'Bob', status: status ?? 'paid',    total: 200 },
  ],
  meta: { page: 1, per_page: 20, total: 2 },
})

describe('useOrders', () => {
  beforeEach(() => {
    vi.mocked(api.fetchOrders).mockResolvedValue(makeResponse() as any)
  })

  it('fetches orders immediately on init', async () => {
    mount(defineComponent({
      setup: () => useOrders(),
      template: '<div></div>',
    }))

    await flushPromises()
    expect(api.fetchOrders).toHaveBeenCalledOnce()
  })

  it('passes status filter to the API call', async () => {
    vi.mocked(api.fetchOrders).mockResolvedValue(makeResponse('payment_pending') as any)

    mount(defineComponent({
      setup() {
        const { filters, orders } = useOrders()
        filters.status = 'payment_pending'
        return { orders }
      },
      template: '<div></div>',
    }))

    await flushPromises()

    const call = vi.mocked(api.fetchOrders).mock.calls.at(-1)![0]
    expect(call.status).toBe('payment_pending')
  })

  it('updates meta with page info from the API response', async () => {
    vi.mocked(api.fetchOrders).mockResolvedValue({
      data: [],
      meta: { page: 2, per_page: 5, total: 50 },
    })

    let capturedMeta: any
    mount(defineComponent({
      setup() {
        const { meta, filters } = useOrders()
        filters.page     = 2
        filters.per_page = 5
        capturedMeta     = meta
        return {}
      },
      template: '<div></div>',
    }))

    await flushPromises()
    expect(capturedMeta.value.total).toBe(50)
    expect(capturedMeta.value.per_page).toBe(5)
  })
})
