<template>
  <div class="queue-panel">
    <!-- Bar chart -->
    <div ref="chartRef" class="chart-wrap"/>

    <!-- Queue table -->
    <table class="queue-table">
      <thead>
      <tr>
        <th>Fila</th>
        <th class="num">Pendentes</th>
        <th class="num">Consumidores</th>
        <th class="num">Pub/s</th>
        <th class="num">Del/s</th>
        <th>Status</th>
      </tr>
      </thead>
      <tbody>
      <tr v-for="q in relevant" :key="q.name">
        <td class="queue-name">{{ q.name }}</td>
        <td :class="q.messages > 0 ? 'warn' : ''" class="num">{{ q.messages }}</td>
        <td class="num">{{ q.consumers }}</td>
        <td class="num rate">{{ fmtRate(q.message_stats?.publish_details?.rate) }}</td>
        <td class="num rate">{{ fmtRate(q.message_stats?.deliver_get_details?.rate) }}</td>
        <td>
          <span :class="healthClass(q)" class="badge">{{ healthLabel(q) }}</span>
        </td>
      </tr>
      <tr v-if="relevant.length === 0">
        <td class="empty" colspan="6">Aguardando dados do broker...</td>
      </tr>
      </tbody>
    </table>
  </div>
</template>

<script lang="ts" setup>
import {computed, onMounted, onUnmounted, ref, watch} from 'vue'
import * as d3 from 'd3'
import type {Queue} from '@/types'

const props = defineProps<{ queues: Queue[] }>()

const chartRef = ref<HTMLElement>()

const SKIP = new Set(['orders.dead', 'orders.retry'])
const relevant = computed(() =>
    props.queues.filter(q => !SKIP.has(q.name)).sort((a, b) => a.name.localeCompare(b.name))
)

function fmtRate(r?: number): string {
  if (r === undefined || r === null) return '—'
  return r < 0.01 ? '0' : r.toFixed(1)
}

function healthClass(q: Queue): string {
  if (q.consumers === 0) return 'badge-error'
  if (q.messages > 100) return 'badge-warn'
  return 'badge-ok'
}

function healthLabel(q: Queue): string {
  if (q.consumers === 0) return 'sem consumer'
  if (q.messages > 100) return 'backlog'
  return 'saudável'
}

function drawChart() {
  const el = chartRef.value
  if (!el) return
  const data = relevant.value

  const W = el.clientWidth || 460
  const H = 90
  const M = {top: 6, right: 8, bottom: 28, left: 8}
  const w = W - M.left - M.right
  const h = H - M.top - M.bottom

  d3.select(el).selectAll('*').remove()

  const svg = d3.select(el)
      .append('svg')
      .attr('width', W)
      .attr('height', H)

  const g = svg.append('g').attr('transform', `translate(${M.left},${M.top})`)

  const x = d3.scaleBand()
      .domain(data.map(q => q.name))
      .range([0, w])
      .padding(0.35)

  const maxMsg = Math.max(1, d3.max(data, q => q.messages) ?? 1)
  const y = d3.scaleLinear().domain([0, maxMsg]).range([h, 0])

  g.selectAll('.bar')
      .data(data)
      .join('rect')
      .attr('class', 'bar')
      .attr('x', q => x(q.name)!)
      .attr('width', x.bandwidth())
      .attr('y', q => y(q.messages))
      .attr('height', q => Math.max(2, h - y(q.messages)))
      .attr('rx', 3)
      .attr('fill', q => q.messages > 0 ? 'var(--accent-primary)' : 'var(--border-secondary)')

  g.append('g')
      .attr('transform', `translate(0,${h})`)
      .call(d3.axisBottom(x).tickSize(0))
      .call(ax => ax.select('.domain').remove())
      .selectAll('text')
      .style('font-size', '9px')
      .style('fill', 'var(--text-tertiary)')
      .text(d => (d as string).replace('orders.', ''))
}

watch(relevant, drawChart)
onMounted(() => {
  drawChart();
  window.addEventListener('resize', drawChart)
})
onUnmounted(() => window.removeEventListener('resize', drawChart))
</script>

<style scoped>
.queue-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.chart-wrap {
  width: 100%;
}

.queue-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.queue-table th {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-tertiary);
  padding: 4px 8px 6px;
  text-align: left;
  border-bottom: 1px solid var(--border-primary);
}

.queue-table td {
  padding: 5px 8px;
  color: var(--text-secondary);
  border-bottom: 1px solid var(--border-secondary);
}

.queue-table tr:last-child td {
  border-bottom: none;
}

.queue-table th.num,
.queue-table td.num {
  text-align: right;
  font-family: var(--font-mono);
}

.queue-name {
  color: var(--text-primary);
  font-family: var(--font-mono);
  font-size: 11px;
}

.warn {
  color: var(--warning, #ffab40) !important;
}

.rate {
  color: var(--text-tertiary);
}

.badge {
  display: inline-block;
  padding: 2px 7px;
  border-radius: var(--radius-full, 99px);
  font-size: 10px;
  font-weight: 600;
}

.badge-ok {
  background: rgba(0, 230, 118, .12);
  color: var(--success);
}

.badge-warn {
  background: rgba(255, 171, 64, .12);
  color: #ffab40;
}

.badge-error {
  background: rgba(255, 61, 0, .12);
  color: var(--error);
}

.empty {
  text-align: center;
  color: var(--text-tertiary);
  padding: 16px;
  font-size: 12px;
}
</style>
