<template>
  <div class="event-flow-chart">
    <div v-if="events.length === 0" data-testid="chart-empty" class="chart-empty">
      Sem eventos para visualizar
    </div>
    <svg v-else ref="svgEl" data-testid="event-flow-svg" class="flow-svg" />
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, nextTick, computed } from 'vue'
import * as d3 from 'd3'
import type { OrderEvent } from '@/types'

const props = defineProps<{ events: OrderEvent[] }>()
const svgEl = ref<SVGSVGElement | null>(null)

// Count transitions between event types
const transitionCounts = computed(() => {
  const sorted = [...props.events].sort((a, b) => a.published_at.localeCompare(b.published_at))
  const counts = new Map<string, number>()
  const nodeCounts = new Map<string, number>()

  for (let i = 1; i < sorted.length; i++) {
    const key = `${sorted[i - 1].event_type}→${sorted[i].event_type}`
    counts.set(key, (counts.get(key) ?? 0) + 1)
  }
  sorted.forEach(e => nodeCounts.set(e.event_type, (nodeCounts.get(e.event_type) ?? 0) + 1))

  return { transitions: counts, nodes: nodeCounts }
})

function draw() {
  const el = svgEl.value
  if (!el || props.events.length === 0) return

  const { nodes } = transitionCounts.value
  const nodeList = Array.from(nodes.entries()).map(([id, count]) => ({ id, count }))

  const W = el.clientWidth || 480
  const H = 220
  d3.select(el).selectAll('*').remove()
  d3.select(el).attr('viewBox', `0 0 ${W} ${H}`)

  const colorScale: Record<string, string> = {
    'order.created':          '#3b82f6',
    'order.payment.pending':  '#f59e0b',
    'order.payment.approved': '#10b981',
    'order.payment.refused':  '#ef4444',
    'order.picking':          '#8b5cf6',
    'order.shipped':          '#06b6d4',
    'order.delivered':        '#10b981',
    'order.cancelled':        '#ef4444',
  }

  // Layout: evenly spaced in a row
  const cols    = Math.min(nodeList.length, 4)
  const rows    = Math.ceil(nodeList.length / cols)
  const cellW   = W / cols
  const cellH   = H / rows

  const positions = new Map<string, { x: number; y: number }>()
  nodeList.forEach((node, i) => {
    const col = i % cols
    const row = Math.floor(i / cols)
    positions.set(node.id, {
      x: cellW * col + cellW / 2,
      y: cellH * row + cellH / 2,
    })
  })

  const maxCount = Math.max(...nodeList.map(n => n.count), 1)
  const rScale   = d3.scaleSqrt().domain([0, maxCount]).range([8, 24])

  const svg = d3.select(el)

  // Node circles
  nodeList.forEach(node => {
    const pos   = positions.get(node.id)!
    const color = colorScale[node.id] ?? '#6b7280'
    const r     = rScale(node.count)

    svg.append('circle')
      .attr('cx', pos.x).attr('cy', pos.y).attr('r', r)
      .attr('fill', color)
      .attr('fill-opacity', 0.2)
      .attr('stroke', color)
      .attr('stroke-width', 1.5)

    svg.append('text')
      .attr('x', pos.x).attr('y', pos.y + r + 12)
      .attr('text-anchor', 'middle')
      .attr('fill', 'rgba(255,255,255,0.5)')
      .attr('font-size', 9)
      .text(node.id.replace('order.', ''))

    svg.append('text')
      .attr('x', pos.x).attr('y', pos.y + 4)
      .attr('text-anchor', 'middle')
      .attr('fill', color)
      .attr('font-size', 11)
      .attr('font-weight', 600)
      .text(node.count)
  })
}

onMounted(() => nextTick(draw))
watch(() => props.events, () => nextTick(draw), { deep: true })
</script>

<style scoped>
.event-flow-chart { width: 100%; }
.chart-empty {
  color: var(--color-text-muted);
  padding: 40px;
  text-align: center;
  font-size: 13px;
}
.flow-svg {
  width: 100%;
  height: 220px;
  display: block;
}
</style>
