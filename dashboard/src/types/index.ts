export interface OrderItem {
    product: string
    qty: number
    price: number
}

export interface Order {
    id: string
    customer_name: string
    customer_email: string
    items: OrderItem[]
    total: number
    status: string
    idempotency_key?: string
    metadata: Record<string, unknown>
    created_at: string
    updated_at: string
}

export interface OrderDetail extends Order {
    events: OrderEvent[]
}

export interface OrderEvent {
    id: string
    order_id: string
    event_type: string
    routing_key: string
    payload: Record<string, unknown>
    worker_id?: string
    attempt: number
    processed: boolean
    error?: string
    published_at: string
    processed_at?: string
}

export interface Consumer {
    worker_id: string
    worker_type: string
    queue_name: string
    status: 'active' | 'idle' | 'stopped'
    last_heartbeat: string
    events_processed: number
    events_failed: number
    started_at: string
}

export interface Stats {
    orders: Record<string, number>
    events: {
        published_last_hour: number
        processed_last_hour: number
        failed_last_hour: number
        dead: number
    }
    consumers: {
        active: number
        idle: number
        stopped: number
    }
}

export interface FunnelItem {
    status: string
    count: number
}

export interface ThroughputPoint {
    minute: string
    count: number
}

export interface EventTypeCount {
    event_type: string
    count: number
}

export interface Queue {
    name: string
    messages: number
    consumers: number
    message_stats?: {
        publish?: number
        publish_details?: { rate: number }
        deliver_get?: number
        deliver_get_details?: { rate: number }
        ack?: number
        ack_details?: { rate: number }
    }
}

export interface OrdersResponse {
    data: Order[]
    meta: { page: number; per_page: number; total: number }
}

export interface MailNotification {
    id: string
    subject: string
    from: string
    to: string
    snippet: string
    created: string
    read: boolean
}

export interface MailNotificationDetail extends MailNotification {
    text: string
}

export interface NotificationsResponse {
    total: number
    messages: MailNotification[]
}

export interface OrdersParams {
    status?: string
    page?: number | string
    per_page?: number | string
}
