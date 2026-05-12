import type {
  Stats, FunnelItem, ThroughputPoint, Consumer,
  OrderEvent, EventTypeCount, Queue,
  OrdersResponse, OrderDetail, OrdersParams,
} from '@/types'

const BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

async function get<T>(path: string): Promise<T> {
  const res = await fetch(`${BASE}${path}`)
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${path}`)
  return res.json()
}

async function post<T>(path: string): Promise<T> {
  const res = await fetch(`${BASE}${path}`, { method: 'POST', headers: { 'Content-Type': 'application/json' } })
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${path}`)
  return res.json()
}

export const api = {
  fetchStats:       ()              => get<Stats>('/api/dashboard/stats'),
  fetchThroughput:  ()              => get<ThroughputPoint[]>('/api/dashboard/throughput'),
  fetchFunnel:      ()              => get<FunnelItem[]>('/api/dashboard/funnel'),
  fetchConsumers:   ()              => get<Consumer[]>('/api/dashboard/consumers'),
  fetchEventFeed:   (limit = 50)    => get<OrderEvent[]>(`/api/dashboard/events/feed?limit=${limit}`),
  fetchEventsByType:()              => get<EventTypeCount[]>('/api/dashboard/events/by-type'),
  fetchQueues:      ()              => get<Queue[]>('/api/dashboard/queues'),
  fetchOrders:      (p: OrdersParams) => {
    const q = new URLSearchParams()
    if (p.status)   q.set('status',   String(p.status))
    if (p.page)     q.set('page',     String(p.page))
    if (p.per_page) q.set('per_page', String(p.per_page))
    return get<OrdersResponse>(`/api/orders?${q}`)
  },
  fetchOrder:       (id: string)   => get<OrderDetail>(`/api/orders/${id}`),
  payOrder:         (id: string)   => post<OrderDetail>(`/api/orders/${id}/pay`),
  cancelOrder:      (id: string)   => post<OrderDetail>(`/api/orders/${id}/cancel`),
  advanceOrder:     (id: string)   => post<OrderDetail>(`/api/orders/${id}/advance`),
}
