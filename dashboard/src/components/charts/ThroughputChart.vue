<template>
  <div class="throughput-chart">
    <div v-if="data.length === 0" class="chart-empty" data-testid="chart-empty">
      Sem dados de throughput
    </div>
    <svg v-else ref="svgEl" class="chart-svg"/>
  </div>
</template>

<script lang="ts" setup>
import {nextTick, onMounted, ref, watch} from 'vue'
import * as d3 from 'd3'
import type {ThroughputPoint} from '@/types'

const props = defineProps<{ data: ThroughputPoint[] }>()
const svgEl = ref<SVGSVGElement | null>(null)

const MARGIN = {top: 16, right: 16, bottom: 28, left: 36}
const HEIGHT = 160

const tooltip = ref<{ visible: boolean; x: number; y: number; text: string }>({
  visible: false, x: 0, y: 0, text: '',
})

function draw() {
  const el = svgEl.value
  if (!el || props.data.length === 0) return

  const totalWidth = el.clientWidth || 600
  const width = totalWidth - MARGIN.left - MARGIN.right
  const height = HEIGHT - MARGIN.top - MARGIN.bottom

  d3.select(el).selectAll('*').remove()

  const svg = d3.select(el)
      .attr('viewBox', `0 0 ${totalWidth} ${HEIGHT}`)
      .attr('preserveAspectRatio', 'xMinYMin meet')
      .append('g')
      .attr('transform', `translate(${MARGIN.left},${MARGIN.top})`)

  const x = d3.scalePoint<string>()
      .domain(props.data.map(d => d.minute))
      .range([0, width])
      .padding(0.1)

  const y = d3.scaleLinear()
      .domain([0, d3.max(props.data, d => d.count) ?? 1])
      .nice()
      .range([height, 0])

  // Gradient definition
  const defs = d3.select(el).append('defs')
  const grad = defs.append('linearGradient')
      .attr('id', 'throughput-gradient')
      .attr('x1', '0').attr('y1', '0')
      .attr('x2', '0').attr('y2', '1')
  grad.append('stop').attr('offset', '0%').attr('stop-color', '#f59e0b').attr('stop-opacity', 0.35)
  grad.append('stop').attr('offset', '100%').attr('stop-color', '#f59e0b').attr('stop-opacity', 0.02)

  // Grid lines
  svg.append('g')
      .attr('class', 'grid')
      .call(
          d3.axisLeft(y).tickSize(-width).tickFormat(() => '').ticks(4)
      )
      .call(g => {
        g.select('.domain').remove()
        g.selectAll('.tick line')
            .attr('stroke', 'rgba(255,255,255,0.06)')
            .attr('stroke-dasharray', '3,3')
      })

  // X axis
  const tickEvery = Math.max(1, Math.floor(props.data.length / 6))
  svg.append('g')
      .attr('transform', `translate(0,${height})`)
      .call(
          d3.axisBottom(x)
              .tickValues(props.data.filter((_, i) => i % tickEvery === 0).map(d => d.minute))
              .tickFormat(d => String(d).slice(-5))
      )
      .call(g => {
        g.select('.domain').attr('stroke', 'rgba(255,255,255,0.1)')
        g.selectAll('.tick line').remove()
        g.selectAll('.tick text').attr('fill', 'rgba(255,255,255,0.4)').attr('font-size', '10')
      })

  // Area
  const area = d3.area<ThroughputPoint>()
      .x(d => x(d.minute) ?? 0)
      .y0(height)
      .y1(d => y(d.count))
      .curve(d3.curveCatmullRom.alpha(0.5))

  svg.append('path')
      .datum(props.data)
      .attr('fill', 'url(#throughput-gradient)')
      .attr('d', area)

  // Line
  const line = d3.line<ThroughputPoint>()
      .x(d => x(d.minute) ?? 0)
      .y(d => y(d.count))
      .curve(d3.curveCatmullRom.alpha(0.5))

  svg.append('path')
      .datum(props.data)
      .attr('fill', 'none')
      .attr('stroke', '#f59e0b')
      .attr('stroke-width', 2)
      .attr('d', line)

  // Tooltip overlay
  const bisect = d3.bisector<ThroughputPoint, string>(d => d.minute).left

  svg.append('rect')
      .attr('width', width)
      .attr('height', height)
      .attr('fill', 'transparent')
      .style('cursor', 'crosshair')
      .on('mousemove', (event) => {
        const [mx] = d3.pointer(event)
        const domain = props.data.map(d => d.minute)
        const eachBand = width / (domain.length - 1 || 1)
        const idx = Math.min(Math.round(mx / eachBand), props.data.length - 1)
        const pt = props.data[Math.max(0, idx)]
        if (!pt) return
        tooltip.value = {
          visible: true,
          x: (x(pt.minute) ?? 0) + MARGIN.left,
          y: y(pt.count) + MARGIN.top,
          text: `${pt.minute.slice(-5)} — ${pt.count} pedidos`,
        }
      })
      .on('mouseleave', () => {
        tooltip.value.visible = false
      })
}

onMounted(() => nextTick(draw))
watch(() => props.data, () => nextTick(draw), {deep: true})
</script>

<style scoped>
.throughput-chart {
  width: 100%;
  position: relative;
}

.chart-empty {
  color: var(--color-text-muted);
  padding: 40px;
  text-align: center;
  font-size: 13px;
}

.chart-svg {
  width: 100%;
  height: 160px;
  display: block;
  overflow: visible;
}
</style>
