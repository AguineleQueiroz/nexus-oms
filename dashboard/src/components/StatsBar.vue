<template>
  <div class="stats-bar">
    <div
      v-for="card in cards"
      :key="card.label"
      data-testid="stat-card"
      class="stat-card"
    >
      <span class="stat-label">{{ card.label }}</span>
      <span class="stat-value">{{ card.value }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Stats } from '@/types'

const props = defineProps<{ stats: Stats }>()

const cards = computed(() => [
  { label: 'Total Orders',     value: props.stats.orders.total ?? 0 },
  { label: 'Events / hr',      value: props.stats.events.published_last_hour ?? 0 },
  { label: 'Failed Events',    value: props.stats.events.failed_last_hour ?? 0 },
  { label: 'Active Consumers', value: props.stats.consumers.active ?? 0 },
])
</script>

<style scoped>
.stats-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.stat-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.stat-label {
  font-size: 12px;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.stat-value {
  font-size: 28px;
  font-weight: 600;
  color: var(--color-text);
}
</style>
