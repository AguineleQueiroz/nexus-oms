<template>
  <div class="order-timeline">
    <div
      v-for="event in sorted"
      :key="event.id"
      data-testid="timeline-entry"
      class="timeline-entry"
    >
      <div class="entry-marker" :class="event.processed ? 'done' : 'pending'" />
      <div class="entry-body">
        <span class="entry-type">{{ event.event_type }}</span>
        <span class="entry-time">{{ formatTime(event.published_at) }}</span>
        <details class="entry-payload">
          <summary>payload</summary>
          <pre>{{ JSON.stringify(event.payload, null, 2) }}</pre>
        </details>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { OrderEvent } from '@/types'

const props = defineProps<{ events: OrderEvent[] }>()

const sorted = computed(() =>
  [...props.events].sort((a, b) => a.published_at.localeCompare(b.published_at))
)

function formatTime(iso: string): string {
  return new Date(iso).toLocaleString('pt-BR')
}
</script>

<style scoped>
.order-timeline {
  display: flex;
  flex-direction: column;
  gap: 0;
  position: relative;
  padding-left: 24px;
}
.order-timeline::before {
  content: '';
  position: absolute;
  left: 7px;
  top: 8px;
  bottom: 8px;
  width: 2px;
  background: var(--color-border);
}
.timeline-entry {
  display: flex;
  gap: 16px;
  padding: 12px 0;
}
.entry-marker {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  margin-left: -24px;
  margin-top: 2px;
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}
.entry-marker.done    { background: var(--color-green); }
.entry-marker.pending { background: var(--color-yellow); border: 2px solid var(--color-yellow); background: transparent; }
.entry-body {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.entry-type {
  font-family: var(--font-mono);
  font-size: 12px;
  color: var(--color-primary);
}
.entry-time {
  font-size: 11px;
  color: var(--color-text-muted);
}
.entry-payload summary {
  font-size: 11px;
  color: var(--color-text-muted);
  cursor: pointer;
}
.entry-payload pre {
  font-family: var(--font-mono);
  font-size: 11px;
  color: var(--color-text);
  background: var(--color-surface-2);
  border-radius: var(--radius-sm);
  padding: 8px;
  margin-top: 4px;
  white-space: pre-wrap;
  word-break: break-all;
}
</style>
