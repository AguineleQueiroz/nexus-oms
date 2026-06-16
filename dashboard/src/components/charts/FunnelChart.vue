<template>
  <div ref="container" class="funnel-chart">
    <div v-if="data.length === 0" class="funnel-empty">Sem dados de pedidos</div>
    <div
        v-for="item in data"
        :key="item.status"
        class="funnel-row"
    >
      <span class="funnel-label">{{ formatLabel(item.status) }}</span>
      <div class="funnel-track">
        <div
            :data-status="item.status"
            :style="{ width: barWidth(item.count) }"
            class="funnel-bar"
            data-testid="funnel-bar"
        />
      </div>
      <span class="funnel-count">{{ item.count }}</span>
    </div>
  </div>
</template>

<script lang="ts" setup>
import {computed, nextTick, onMounted, ref, watch} from 'vue'
import * as d3 from 'd3'
import type {FunnelItem} from '@/types'

const props = defineProps<{ data: FunnelItem[] }>()
const container = ref<HTMLElement | null>(null)

const maxCount = computed(() => Math.max(...props.data.map(d => d.count), 1))

function barWidth(count: number): string {
  if (count === 0) return '4px'
  return `${Math.round((count / maxCount.value) * 100)}%`
}

function formatLabel(status: string): string {
  const labels: Record<string, string> = {
    created: 'Criado',
    payment_pending: 'Aguard. Pgto',
    paid: 'Pago',
    picking: 'Separando',
    shipped: 'Enviado',
    delivered: 'Entregue',
    cancelled: 'Cancelado',
    payment_refused: 'Pgto Recusado',
  }
  return labels[status] ?? status
}

function animateBars() {
  if (!container.value) return
  const max = maxCount.value
  d3.select(container.value)
      .selectAll<HTMLElement, FunnelItem>('[data-testid="funnel-bar"]')
      .data(props.data, d => (d as unknown as FunnelItem)?.status ?? '')
      .transition()
      .duration(600)
      .ease(d3.easeQuadOut)
      .style('width', (_, i) => {
        const item = props.data[i]
        return item ? `${Math.round((item.count / max) * 100)}%` : '0%'
      })
}

onMounted(() => nextTick(animateBars))
watch(() => props.data, () => nextTick(animateBars), {deep: true})
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
  background: rgba(255, 255, 255, 0.05);
  border-radius: 99px;
  height: 18px;
  overflow: hidden;
}

.funnel-bar {
  height: 100%;
  background: var(--color-amber, #f59e0b);
  border-radius: 99px;
}

.funnel-empty {
  color: var(--color-text-muted, #707070);
  font-size: 13px;
  text-align: center;
  padding: 20px 0;
}

.funnel-count {
  font-size: 12px;
  color: var(--color-text-muted);
  text-align: right;
  font-family: var(--font-mono);
}
</style>
