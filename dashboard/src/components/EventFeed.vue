<template>
  <div class="event-feed">
    <div v-if="events.length === 0" data-testid="event-empty" class="event-empty">
      Nenhum evento recente
    </div>
    <div
      v-for="event in events"
      :key="event.id"
      data-testid="event-row"
      class="event-row"
    >
      <span class="event-id">{{ event.id }}</span>
      <code class="event-routing-key">{{ event.routing_key }}</code>
      <span class="event-time">{{ formatTime(event.published_at) }}</span>
      <span :class="['event-status', event.processed ? 'processed' : 'pending']">
        {{ event.processed ? 'ok' : 'pending' }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { OrderEvent } from '@/types'

defineProps<{ events: OrderEvent[] }>()

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}
</script>

<style scoped>
.event-feed {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.event-empty {
  color: var(--color-text-muted);
  padding: 24px;
  text-align: center;
}
.event-row {
  display: grid;
  grid-template-columns: minmax(80px, 1fr) 2fr 120px 60px;
  gap: 12px;
  align-items: center;
  padding: 8px 12px;
  background: var(--color-surface);
  border-radius: var(--radius-sm);
  font-size: 13px;
  transition: background 0.15s;
}
.event-row:hover {
  background: var(--color-surface-2);
}
.event-id {
  color: var(--color-text-muted);
  font-family: var(--font-mono);
  font-size: 11px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.event-routing-key {
  font-family: var(--font-mono);
  font-size: 12px;
  color: var(--color-primary);
}
.event-time {
  color: var(--color-text-muted);
  font-size: 12px;
}
.event-status {
  font-size: 11px;
  font-weight: 500;
  text-transform: uppercase;
}
.processed { color: var(--color-green); }
.pending   { color: var(--color-yellow); }
</style>
