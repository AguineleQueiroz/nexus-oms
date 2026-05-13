<template>
  <div class="order-timeline">
    <div
      v-for="event in sorted"
      :key="event.id"
      data-testid="timeline-entry"
      :data-event-type="event.event_type"
      class="timeline-entry"
      @mouseenter="showTooltip($event, event)"
      @mouseleave="hideTooltip"
    >
      <div class="entry-spine">
        <div :class="['entry-dot', dotClass(event.event_type)]" />
        <div class="entry-line" />
      </div>
      <div class="entry-body">
        <span class="entry-type">{{ event.event_type }}</span>
        <span class="entry-time">{{ formatTime(event.published_at) }}</span>
        <details class="entry-payload">
          <summary>payload</summary>
          <pre>{{ JSON.stringify(event.payload, null, 2) }}</pre>
        </details>
      </div>
    </div>

    <!-- D3 SVG decoration (non-blocking for tests) -->
    <svg
      ref="svgEl"
      class="timeline-svg"
      :style="{ height: svgHeight + 'px' }"
      aria-hidden="true"
    />

    <!-- Tooltip -->
    <div
      v-if="tooltip.visible"
      class="timeline-tooltip"
      :style="{ top: tooltip.y + 'px', left: tooltip.x + 'px' }"
    >
      <div class="tooltip-type">{{ tooltip.eventType }}</div>
      <pre class="tooltip-payload">{{ JSON.stringify(tooltip.payload, null, 2) }}</pre>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, watch, nextTick } from 'vue'
import * as d3 from 'd3'
import type { OrderEvent } from '@/types'

const props = defineProps<{ events: OrderEvent[] }>()

const svgEl    = ref<SVGSVGElement | null>(null)
const svgHeight = ref(0)
const tooltip  = ref({ visible: false, x: 0, y: 0, eventType: '', payload: {} as Record<string, unknown> })

const sorted = computed(() =>
  [...props.events].sort((a, b) => a.published_at.localeCompare(b.published_at))
)

function formatTime(iso: string): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('pt-BR')
}

function dotClass(eventType: string): string {
  if (eventType.includes('approved') || eventType === 'order.delivered') return 'dot-success'
  if (eventType.includes('refused') || eventType === 'order.cancelled')  return 'dot-danger'
  if (eventType.includes('payment'))                                      return 'dot-warning'
  return 'dot-info'
}

function showTooltip(e: MouseEvent, event: OrderEvent) {
  tooltip.value = {
    visible: true,
    x: (e.target as HTMLElement).getBoundingClientRect().left + 32,
    y: (e.target as HTMLElement).getBoundingClientRect().top,
    eventType: event.event_type,
    payload: event.payload,
  }
}

function hideTooltip() {
  tooltip.value.visible = false
}

function drawSvg() {
  const el = svgEl.value
  if (!el || sorted.value.length === 0) return

  const rowH   = 72
  const height = sorted.value.length * rowH
  svgHeight.value = height

  d3.select(el).selectAll('*').remove()

  const svg = d3.select(el)
    .attr('width', 16)
    .attr('height', height)

  // Vertical line
  svg.append('line')
    .attr('x1', 8).attr('x2', 8)
    .attr('y1', 8).attr('y2', height - 8)
    .attr('stroke', 'rgba(255,255,255,0.1)')
    .attr('stroke-width', 2)

  // Dots per event
  const colorMap: Record<string, string> = {
    'dot-success': '#10b981',
    'dot-danger':  '#ef4444',
    'dot-warning': '#f59e0b',
    'dot-info':    '#3b82f6',
  }

  sorted.value.forEach((event, i) => {
    const cy    = 8 + i * rowH + rowH / 2 - rowH / 2
    const color = colorMap[dotClass(event.event_type)] ?? '#3b82f6'

    svg.append('circle')
      .attr('cx', 8).attr('cy', cy + 8)
      .attr('r', 5)
      .attr('fill', color)
      .attr('stroke', 'rgba(0,0,0,0.5)')
      .attr('stroke-width', 1.5)
  })
}

onMounted(() => nextTick(drawSvg))
watch(sorted, () => nextTick(drawSvg))
</script>

<style scoped>
.order-timeline {
  display: flex;
  flex-direction: column;
  gap: 0;
  position: relative;
}
.timeline-entry {
  display: grid;
  grid-template-columns: 24px 1fr;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-border, rgba(255,255,255,0.08));
  cursor: default;
}
.timeline-entry:last-child { border-bottom: none; }
.timeline-entry:hover { background: rgba(255,255,255,0.02); }

.entry-spine {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 4px;
}
.entry-dot {
  width: 10px; height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
  z-index: 1;
}
.dot-success { background: #10b981; }
.dot-danger  { background: #ef4444; }
.dot-warning { background: #f59e0b; }
.dot-info    { background: #3b82f6; }

.entry-line {
  flex: 1;
  width: 2px;
  background: rgba(255,255,255,0.08);
  margin-top: 4px;
}
.timeline-entry:last-child .entry-line { display: none; }

.entry-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.entry-type {
  font-family: var(--font-mono, monospace);
  font-size: 12px;
  color: var(--color-text, #f4f4f5);
  font-weight: 600;
}
.entry-time {
  font-size: 11px;
  color: var(--color-text-muted, #71717a);
}
.entry-payload summary {
  font-size: 11px;
  color: var(--color-text-muted, #71717a);
  cursor: pointer;
  user-select: none;
}
.entry-payload pre {
  font-size: 11px;
  font-family: var(--font-mono, monospace);
  color: var(--color-text-muted, #71717a);
  margin: 4px 0 0;
  white-space: pre-wrap;
  word-break: break-all;
}

.timeline-svg {
  position: absolute;
  left: 0; top: 0;
  width: 16px;
  pointer-events: none;
  display: none; /* decorative only; hidden by default */
}

.timeline-tooltip {
  position: fixed;
  background: var(--color-surface, #111113);
  border: 1px solid var(--color-border, #2a2a2e);
  border-radius: 6px;
  padding: 10px 12px;
  z-index: 100;
  pointer-events: none;
  max-width: 320px;
}
.tooltip-type { font-family: var(--font-mono, monospace); font-size: 12px; font-weight: 600; margin-bottom: 6px; }
.tooltip-payload { font-size: 11px; margin: 0; white-space: pre-wrap; word-break: break-all; color: var(--color-text-muted, #71717a); }
</style>
