<template>
  <div class="throughput-chart">
    <div v-if="data.length === 0" data-testid="chart-empty" class="chart-empty">
      Sem dados de throughput
    </div>
    <svg v-else :width="width" :height="height" class="chart-svg">
      <defs>
        <linearGradient id="area-gradient" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%"   stop-color="var(--color-amber)" stop-opacity="0.3" />
          <stop offset="100%" stop-color="var(--color-amber)" stop-opacity="0.02" />
        </linearGradient>
      </defs>
      <path :d="areaPath" fill="url(#area-gradient)" />
      <path :d="linePath" fill="none" stroke="var(--color-amber)" stroke-width="2" />
    </svg>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { ThroughputPoint } from '@/types'

const props = defineProps<{ data: ThroughputPoint[] }>()

const width  = ref(600)
const height = ref(160)
const paddingX = 8
const paddingY = 12

const xScale = computed(() => {
  const n = props.data.length
  return (i: number) => paddingX + (i / Math.max(n - 1, 1)) * (width.value - paddingX * 2)
})

const yScale = computed(() => {
  const max = Math.max(...props.data.map(d => d.count), 1)
  return (v: number) => height.value - paddingY - (v / max) * (height.value - paddingY * 2)
})

const linePath = computed(() => {
  return props.data.map((d, i) =>
    `${i === 0 ? 'M' : 'L'}${xScale.value(i)},${yScale.value(d.count)}`
  ).join(' ')
})

const areaPath = computed(() => {
  const bottom = height.value - paddingY
  const points = props.data.map((d, i) => `L${xScale.value(i)},${yScale.value(d.count)}`).join(' ')
  const first  = `M${xScale.value(0)},${bottom}`
  const close  = `L${xScale.value(props.data.length - 1)},${bottom}Z`
  return first + ' ' + points.replace(/^L/, '') + ' ' + close
})
</script>

<style scoped>
.throughput-chart {
  width: 100%;
}
.chart-empty {
  color: var(--color-text-muted);
  padding: 40px;
  text-align: center;
  font-size: 13px;
}
.chart-svg {
  width: 100%;
  height: auto;
  display: block;
}
</style>
