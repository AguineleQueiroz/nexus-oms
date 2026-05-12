<template>
  <div class="funnel-chart">
    <div
      v-for="item in data"
      :key="item.status"
      class="funnel-row"
    >
      <span class="funnel-label">{{ formatLabel(item.status) }}</span>
      <div class="funnel-track">
        <div
          data-testid="funnel-bar"
          :data-status="item.status"
          class="funnel-bar"
          :style="{ width: barWidth(item.count) }"
        />
      </div>
      <span class="funnel-count">{{ item.count }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { FunnelItem } from '@/types'

const props = defineProps<{ data: FunnelItem[] }>()

const maxCount = computed(() => Math.max(...props.data.map(d => d.count), 1))

function barWidth(count: number): string {
  return `${Math.round((count / maxCount.value) * 100)}%`
}

function formatLabel(status: string): string {
  const labels: Record<string, string> = {
    created:         'Criado',
    payment_pending: 'Aguard. Pgto',
    paid:            'Pago',
    preparing:       'Preparando',
    ready:           'Pronto',
    in_transit:      'Em Trânsito',
    delivered:       'Entregue',
    cancelled:       'Cancelado',
  }
  return labels[status] ?? status
}
</script>

<style scoped>
.funnel-chart {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.funnel-row {
  display: grid;
  grid-template-columns: 130px 1fr 50px;
  align-items: center;
  gap: 12px;
}
.funnel-label {
  font-size: 12px;
  color: var(--color-text-muted);
  text-align: right;
}
.funnel-track {
  background: var(--color-surface-2);
  border-radius: 99px;
  height: 18px;
  overflow: hidden;
}
.funnel-bar {
  height: 100%;
  background: var(--color-primary);
  border-radius: 99px;
  transition: width 0.4s ease;
  min-width: 2px;
}
.funnel-count {
  font-size: 12px;
  color: var(--color-text-muted);
  text-align: right;
}
</style>
