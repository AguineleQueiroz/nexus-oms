<template>
  <div class="stats-bar">
    <div
        v-for="(card, i) in cards"
        :key="card.label"
        :style="{ '--card-accent': card.color }"
        class="stat-card"
        data-testid="stat-card"
    >
      <div class="stat-header">
        <span class="stat-label">{{ card.label }}</span>
        <div :style="{ background: card.color + '18', color: card.color }" class="stat-icon">
          <component :is="card.icon"/>
        </div>
      </div>
      <span
          :ref="el => setRef(el as HTMLElement | null, i)"
          class="stat-value"
      />
      <div :style="{ background: card.color }" class="stat-bar"/>
    </div>
  </div>
</template>

<script lang="ts" setup>
import {computed, h, onMounted, ref, watch} from 'vue'
import {CountUp} from 'countup.js'
import type {Stats} from '@/types'

const props = defineProps<{ stats: Stats }>()

const IconBox = () => h('svg', {viewBox: '0 0 14 14', fill: 'none', width: 14, height: 14}, [
  h('rect', {x: 1, y: 1, width: 5, height: 5, rx: 1, fill: 'currentColor'}),
  h('rect', {x: 8, y: 1, width: 5, height: 5, rx: 1, fill: 'currentColor', opacity: .6}),
  h('rect', {x: 1, y: 8, width: 5, height: 5, rx: 1, fill: 'currentColor', opacity: .6}),
  h('rect', {x: 8, y: 8, width: 5, height: 5, rx: 1, fill: 'currentColor', opacity: .6}),
])
const IconZap = () => h('svg', {viewBox: '0 0 14 14', fill: 'none', width: 14, height: 14}, [
  h('path', {d: 'M8 1L3 8h4l-1 5 6-7H8l1-5z', fill: 'currentColor'}),
])
const IconAlert = () => h('svg', {viewBox: '0 0 14 14', fill: 'none', width: 14, height: 14}, [
  h('path', {d: 'M7 1L1 12h12L7 1z', stroke: 'currentColor', 'stroke-width': 1.5, 'stroke-linejoin': 'round'}),
  h('path', {d: 'M7 5v3M7 10v.5', stroke: 'currentColor', 'stroke-width': 1.5, 'stroke-linecap': 'round'}),
])
const IconUsers = () => h('svg', {viewBox: '0 0 14 14', fill: 'none', width: 14, height: 14}, [
  h('circle', {cx: 5, cy: 4, r: 2, stroke: 'currentColor', 'stroke-width': 1.5}),
  h('path', {
    d: 'M1 12c0-2.21 1.79-4 4-4s4 1.79 4 4',
    stroke: 'currentColor',
    'stroke-width': 1.5,
    'stroke-linecap': 'round'
  }),
  h('path', {
    d: 'M10 2c1.1 0 2 .9 2 2s-.9 2-2 2M13 12c0-1.86-1.28-3.41-3-3.83',
    stroke: 'currentColor',
    'stroke-width': 1.4,
    'stroke-linecap': 'round'
  }),
])

const cards = computed(() => [
  {label: 'Total de Pedidos', value: props.stats.orders.total ?? 0, color: 'var(--accent-primary)', icon: IconBox},
  {label: 'Eventos / hora', value: props.stats.events.published_last_hour ?? 0, color: 'var(--info)', icon: IconZap},
  {label: 'Falhas', value: props.stats.events.failed_last_hour ?? 0, color: 'var(--error)', icon: IconAlert},
  {label: 'Workers ativos', value: props.stats.consumers.active ?? 0, color: 'var(--success)', icon: IconUsers},
])

const els = ref<(HTMLElement | null)[]>([])
const counters = ref<CountUp[]>([])

function setRef(el: HTMLElement | null, i: number) {
  els.value[i] = el
}

onMounted(() => {
  cards.value.forEach((card, i) => {
    const el = els.value[i]
    if (!el) return
    const cu = new CountUp(el, card.value, {duration: 1.4, separator: '.'})
    cu.start()
    counters.value[i] = cu
  })
})

watch(cards, (newCards) => {
  newCards.forEach((card, i) => {
    counters.value[i]?.update(card.value)
  })
})
</script>

<style scoped>
.stats-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.stat-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-primary);
  border-radius: var(--radius-md);
  padding: 16px 18px 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  position: relative;
  overflow: hidden;
  transition: border-color var(--transition-fast);
}

.stat-card:hover {
  border-color: var(--card-accent);
}

.stat-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.stat-label {
  font-size: 11px;
  font-weight: 500;
  color: var(--text-tertiary);
  text-transform: uppercase;
  letter-spacing: 0.07em;
}

.stat-icon {
  width: 26px;
  height: 26px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-value {
  font-family: var(--font-mono);
  font-size: 32px;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1;
  min-height: 1em;
}

.stat-bar {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 2px;
  opacity: 0.6;
}
</style>
