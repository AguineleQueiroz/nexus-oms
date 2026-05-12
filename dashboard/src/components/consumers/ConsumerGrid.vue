<template>
  <div class="consumer-grid">
    <div
      v-for="consumer in consumers"
      :key="consumer.worker_id"
      data-testid="consumer-card"
      class="consumer-card"
    >
      <div class="card-header">
        <span :class="['status-dot', consumer.status === 'active' ? 'pulse' : '']" />
        <span class="worker-id">{{ consumer.worker_id }}</span>
        <span :class="['status-badge', consumer.status]">{{ consumer.status }}</span>
      </div>
      <div class="card-meta">
        <span>{{ consumer.worker_type }}</span>
        <span class="queue-name">{{ consumer.queue_name }}</span>
      </div>
      <div class="card-stats">
        <span>Processados: <strong>{{ consumer.events_processed }}</strong></span>
        <span>Falhas: <strong>{{ consumer.events_failed }}</strong></span>
      </div>
      <div data-testid="sparkline" class="sparkline">
        <svg width="100%" height="32" class="sparkline-svg">
          <polyline
            :points="sparklinePoints(consumer)"
            fill="none"
            :stroke="consumer.status === 'active' ? 'var(--color-green)' : 'var(--color-border)'"
            stroke-width="1.5"
          />
        </svg>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Consumer } from '@/types'

const props = defineProps<{ consumers: Consumer[] }>()

function sparklinePoints(consumer: Consumer): string {
  const total = consumer.events_processed + consumer.events_failed || 1
  const ratio = consumer.events_processed / total
  const points = [0, ratio * 0.3, ratio * 0.6, ratio * 0.8, ratio, ratio * 0.9, ratio]
  return points.map((v, i) => `${(i / (points.length - 1)) * 100},${32 - v * 28}`).join(' ')
}
</script>

<style scoped>
.consumer-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}
.consumer-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
}
.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--color-green);
  flex-shrink: 0;
}
.worker-id {
  font-family: var(--font-mono);
  font-size: 12px;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.status-badge {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  padding: 2px 6px;
  border-radius: 99px;
}
.status-badge.active  { background: rgba(16,185,129,0.15); color: var(--color-green); }
.status-badge.idle    { background: rgba(234,179,8,0.15);  color: var(--color-yellow); }
.status-badge.stopped { background: rgba(239,68,68,0.15);  color: var(--color-red); }
.card-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
  color: var(--color-text-muted);
}
.queue-name {
  font-family: var(--font-mono);
  font-size: 11px;
}
.card-stats {
  display: flex;
  gap: 16px;
  font-size: 12px;
  color: var(--color-text-muted);
}
.sparkline-svg {
  display: block;
  overflow: visible;
}
</style>
