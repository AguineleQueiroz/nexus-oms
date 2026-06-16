<template>
  <div class="event-feed">
    <div v-if="events.length === 0" class="event-empty" data-testid="event-empty">
      Aguardando eventos…
    </div>
    <TransitionGroup v-else class="feed-list" name="feed" tag="div">
      <div
          v-for="event in events"
          :key="event.id"
          class="event-row"
          data-testid="event-row"
      >
        <span :class="['event-dot', dotClass(event.event_type)]"/>
        <div class="event-main">
          <code class="event-routing-key">{{ event.routing_key }}</code>
          <span class="event-order">{{ event.id.slice(0, 8) }}</span>
        </div>
        <div class="event-meta">
          <span :class="['event-status', event.processed ? 'processed' : 'pending']">
            {{ event.processed ? 'ok' : 'pend' }}
          </span>
          <span class="event-time">{{ formatTime(event.published_at) }}</span>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<script lang="ts" setup>
import type {OrderEvent} from '@/types'

defineProps<{ events: OrderEvent[] }>()

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit', second: '2-digit'})
}

function dotClass(eventType: string): string {
  if (eventType.includes('approved') || eventType === 'order.delivered') return 'dot-success'
  if (eventType.includes('refused') || eventType === 'order.cancelled') return 'dot-error'
  if (eventType.includes('payment')) return 'dot-warning'
  if (eventType === 'order.shipped') return 'dot-info'
  return 'dot-default'
}
</script>

<style scoped>
.event-feed {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.event-empty {
  color: var(--text-tertiary);
  padding: 32px;
  text-align: center;
  font-size: 13px;
}

.feed-list {
  display: flex;
  flex-direction: column;
}

/* TransitionGroup animations */
.feed-enter-active {
  animation: slide-in-top 300ms ease;
}

.feed-leave-active {
  transition: opacity 200ms ease;
  position: absolute;
  width: 100%;
}

.feed-leave-to {
  opacity: 0;
}

.feed-move {
  transition: transform 300ms ease;
}

.event-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-bottom: 1px solid var(--border-secondary);
  transition: background var(--transition-fast);
  font-size: 12px;
}

.event-row:last-child {
  border-bottom: none;
}

.event-row:first-child {
  animation: slide-in-top 300ms ease;
}

.event-row:hover {
  background: rgba(255, 255, 255, 0.02);
}

.event-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}

.dot-success {
  background: var(--success);
}

.dot-error {
  background: var(--error);
}

.dot-warning {
  background: var(--warning);
}

.dot-info {
  background: var(--info);
}

.dot-default {
  background: var(--text-tertiary);
}

.event-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 1px;
  overflow: hidden;
}

.event-routing-key {
  font-family: var(--font-mono);
  font-size: 11px;
  color: var(--accent-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.event-order {
  font-family: var(--font-mono);
  font-size: 10px;
  color: var(--text-tertiary);
}

.event-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 1px;
  flex-shrink: 0;
}

.event-time {
  font-size: 10px;
  color: var(--text-tertiary);
}

.event-status {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.processed {
  color: var(--success);
}

.pending {
  color: var(--warning);
}
</style>
