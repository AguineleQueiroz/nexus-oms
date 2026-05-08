CREATE TABLE consumers_log (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    worker_id        VARCHAR(100) NOT NULL UNIQUE,
    worker_type      VARCHAR(100) NOT NULL,
    queue_name       VARCHAR(100) NOT NULL,
    status           VARCHAR(50) NOT NULL DEFAULT 'active',
    last_heartbeat   TIMESTAMP NOT NULL DEFAULT NOW(),
    events_processed INT NOT NULL DEFAULT 0,
    events_failed    INT NOT NULL DEFAULT 0,
    started_at       TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE processed_events (
    event_id     VARCHAR(255) PRIMARY KEY,
    processed_at TIMESTAMP NOT NULL DEFAULT NOW()
);
