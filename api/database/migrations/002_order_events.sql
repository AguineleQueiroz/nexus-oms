CREATE TABLE order_events
(
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL REFERENCES orders(id),
    event_type   VARCHAR(100) NOT NULL,
    routing_key  VARCHAR(100) NOT NULL,
    payload JSONB NOT NULL,
    worker_id    VARCHAR(100),
    attempt      INT          NOT NULL DEFAULT 1,
    processed    BOOLEAN      NOT NULL DEFAULT FALSE,
    error        TEXT,
    published_at TIMESTAMP    NOT NULL DEFAULT NOW(),
    processed_at TIMESTAMP
);

CREATE INDEX idx_order_events_order_id ON order_events (order_id);
CREATE INDEX idx_order_events_event_type ON order_events (event_type);
CREATE INDEX idx_order_events_published_at ON order_events (published_at DESC);
