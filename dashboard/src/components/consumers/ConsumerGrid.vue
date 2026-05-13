<template>
  <div class="consumer-grid">
    <div v-if="consumers.length === 0" class="grid-empty">
      Nenhum worker registrado. Inicie os workers ou rode o seeder para popular dados.
    </div>
    <div
      v-for="consumer in consumers"
      :key="consumer.worker_id"
      data-testid="consumer-card"
      :class="['consumer-card', consumer.status]"
    >
      <div class="card-header">
        <span :class="['status-dot', consumer.status === 'active' ? 'pulse' : '']" />
        <span class="worker-type">{{ consumer.worker_type }}</span>
        <span :class="['status-badge', consumer.status]">{{ consumer.status }}</span>
      </div>

      <div class="worker-id-row">
        <code class="worker-id">{{ consumer.worker_id }}</code>
        <span class="queue-name">{{ consumer.queue_name }}</span>
      </div>

      <!-- Métricas principais -->
      <div class="metrics-row">
        <div class="metric">
          <span class="metric-value success">{{ consumer.events_processed.toLocaleString('pt-BR') }}</span>
          <span class="metric-label">Processados</span>
        </div>
        <div class="metric-divider" />
        <div class="metric">
          <span :class="['metric-value', consumer.events_failed > 0 ? 'error' : 'muted']">
            {{ consumer.events_failed }}
          </span>
          <span class="metric-label">Falhas</span>
        </div>
        <div class="metric-divider" />
        <div class="metric">
          <span class="metric-value info">{{ heartbeatAgo(consumer.last_heartbeat) }}</span>
          <span class="metric-label">Heartbeat</span>
        </div>
      </div>

      <!-- Sparkline D3 -->
      <div data-testid="sparkline" class="sparkline">
        <svg
          :ref="el => setSvgRef(el as SVGSVGElement | null, consumer.worker_id)"
          width="100%"
          height="36"
          class="sparkline-svg"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, watch } from 'vue'
import * as d3 from 'd3'
import type { Consumer } from '@/types'

const props = defineProps<{ consumers: Consumer[] }>()

const svgRefs = new Map<string, SVGSVGElement>()

function setSvgRef(el: SVGSVGElement | null, workerId: string) {
  if (el) svgRefs.set(workerId, el)
}

function heartbeatAgo(iso: string): string {
  const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (diff < 60) return `${diff}s`
  return `${Math.floor(diff / 60)}m`
}

function drawSparkline(consumer: Consumer) {
  const el = svgRefs.get(consumer.worker_id)
  if (!el) return

  d3.select(el).selectAll('*').remove()

  const total  = consumer.events_processed + consumer.events_failed || 1
  const ratio  = consumer.events_processed / total
  const points = Array.from({ length: 14 }, (_, i) => {
    const noise = Math.sin(i * 1.7 + consumer.events_processed * 0.01) * 0.12
    return Math.max(0.05, Math.min(0.95, ratio + noise))
  })

  const w = el.clientWidth || 200
  const h = 36

  const x = d3.scaleLinear().domain([0, points.length - 1]).range([0, w])
  const y = d3.scaleLinear().domain([0, 1]).range([h - 2, 4])

  const area = d3.area<number>()
    .x((_, i) => x(i))
    .y0(h)
    .y1(d => y(d))
    .curve(d3.curveCatmullRom.alpha(0.5))

  const line = d3.line<number>()
    .x((_, i) => x(i))
    .y(d => y(d))
    .curve(d3.curveCatmullRom.alpha(0.5))

  const color = consumer.status === 'active'
    ? '#00e676'
    : consumer.status === 'idle'
      ? '#ffd600'
      : '#4a4a4a'

  d3.select(el)
    .append('path')
    .datum(points)
    .attr('fill', color)
    .attr('fill-opacity', 0.07)
    .attr('d', area)

  d3.select(el)
    .append('path')
    .datum(points)
    .attr('fill', 'none')
    .attr('stroke', color)
    .attr('stroke-width', 1.5)
    .attr('stroke-linecap', 'round')
    .attr('d', line)
}

function drawAll() {
  props.consumers.forEach(c => setTimeout(() => drawSparkline(c), 0))
}

onMounted(drawAll)
watch(() => props.consumers, drawAll, { deep: true })
</script>

<style scoped>
.consumer-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 12px;
}

.grid-empty {
  grid-column: 1 / -1;
  color: var(--text-tertiary);
  font-size: 13px;
  padding: 40px;
  text-align: center;
  background: var(--bg-secondary);
  border: 1px dashed var(--border-primary);
  border-radius: var(--radius-md);
}

.consumer-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-primary);
  border-radius: var(--radius-md);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: border-color var(--transition-fast);
}
.consumer-card.active  { border-color: rgba(0, 230, 118, 0.2); }
.consumer-card.idle    { border-color: rgba(255, 214, 0, 0.15); }
.consumer-card.stopped { border-color: rgba(255, 61, 0, 0.15); }

.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
}
.status-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--success);
  flex-shrink: 0;
}
.status-dot.pulse { animation: pulse-dot 2s ease-in-out infinite; }

.worker-type {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-primary);
  flex: 1;
}
.status-badge {
  font-size: 9px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 2px 7px;
  border-radius: var(--radius-full);
}
.status-badge.active  { background: var(--success-bg);  color: var(--success); }
.status-badge.idle    { background: var(--warning-bg);  color: var(--warning); }
.status-badge.stopped { background: var(--error-bg);    color: var(--error); }

.worker-id-row {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.worker-id {
  font-family: var(--font-mono);
  font-size: 11px;
  color: var(--text-secondary);
}
.queue-name {
  font-family: var(--font-mono);
  font-size: 10px;
  color: var(--text-tertiary);
}

/* Métricas */
.metrics-row {
  display: flex;
  align-items: center;
  background: var(--bg-tertiary);
  border-radius: var(--radius-sm);
  padding: 8px 12px;
  gap: 8px;
}
.metric {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}
.metric-value {
  font-family: var(--font-mono);
  font-size: 18px;
  font-weight: 700;
  line-height: 1;
}
.metric-value.success { color: var(--success); }
.metric-value.error   { color: var(--error); }
.metric-value.muted   { color: var(--text-tertiary); }
.metric-value.info    { color: var(--info); font-size: 14px; }
.metric-label {
  font-size: 9px;
  color: var(--text-tertiary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.metric-divider {
  width: 1px;
  height: 28px;
  background: var(--border-primary);
  flex-shrink: 0;
}

.sparkline { overflow: hidden; }
.sparkline-svg { display: block; overflow: visible; }
</style>
