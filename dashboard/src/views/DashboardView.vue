<template>
  <div class="dashboard-view">

      <!-- Header -->
      <div class="dash-header">
        <div>
          <h1 class="page-title">Dashboard</h1>
          <p class="page-sub">Visão em tempo real do sistema de pedidos</p>
        </div>
        <div class="last-updated">
          <span class="lu-dot" />
          <span class="lu-text">Atualizado {{ lastUpdatedLabel }}</span>
        </div>
      </div>

      <!-- Stats -->
      <StatsBar v-if="stats" :stats="stats" />
      <div v-else class="stats-skeleton">
        <div v-for="i in 4" :key="i" class="skeleton-card" />
      </div>

      <!-- Workers -->
      <section class="section">
        <div class="section-header">
          <h2 class="section-title">Workers</h2>
          <router-link to="/consumers" class="section-link">Ver todos →</router-link>
        </div>
        <WorkersMini :consumers="consumers" />
      </section>

      <!-- Charts + Feed -->
      <div class="charts-feed-row">
        <div class="charts-col">
          <div class="panel">
            <h2 class="panel-title">Funil de Status</h2>
            <FunnelChart :data="funnel" />
          </div>
          <div class="panel">
            <h2 class="panel-title">Throughput — últimos 60 min</h2>
            <ThroughputChart :data="throughput" />
          </div>
          <div class="panel">
            <div class="queue-panel-header">
              <h2 class="panel-title">Filas RabbitMQ</h2>
              <span class="queue-count">{{ queues.length }} filas</span>
            </div>
            <QueueStatusPanel :queues="queues" />
          </div>
        </div>
        <div class="feed-col">
          <div class="panel panel-feed">
            <div class="feed-header">
              <h2 class="panel-title">Event Feed</h2>
              <span class="feed-count">{{ events.length }} eventos</span>
            </div>
            <div class="feed-scroll">
              <EventFeed :events="events" />
            </div>
          </div>
        </div>
      </div>

    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import StatsBar from '@/components/StatsBar.vue'
import WorkersMini from '@/components/consumers/WorkersMini.vue'
import FunnelChart from '@/components/charts/FunnelChart.vue'
import ThroughputChart from '@/components/charts/ThroughputChart.vue'
import QueueStatusPanel from '@/components/charts/QueueStatusPanel.vue'
import EventFeed from '@/components/EventFeed.vue'
import { useStats } from '@/composables/useStats'
import { useEventFeed } from '@/composables/useEventFeed'
import { useConsumers } from '@/composables/useConsumers'
import { api } from '@/services/api'
import type { ThroughputPoint, FunnelItem, Queue } from '@/types'

const { stats }     = useStats(5000)
const { events }    = useEventFeed(2000)
const { consumers } = useConsumers(5000)

const throughput = ref<ThroughputPoint[]>([])
const funnel     = ref<FunnelItem[]>([])
const queues     = ref<Queue[]>([])
const lastUpdate = ref(Date.now())

const lastUpdatedLabel = computed(() => {
  const s = Math.floor((Date.now() - lastUpdate.value) / 1000)
  if (s < 5) return 'agora'
  if (s < 60) return `${s}s atrás`
  return `${Math.floor(s / 60)}m atrás`
})

async function loadCharts() {
  const [t, f, q] = await Promise.all([
    api.fetchThroughput().catch(() => [] as ThroughputPoint[]),
    api.fetchFunnel().catch(()   => [] as FunnelItem[]),
    api.fetchQueues().catch(()   => [] as Queue[]),
  ])
  throughput.value = t
  funnel.value     = f
  queues.value     = q
  lastUpdate.value = Date.now()
}

let chartTimer: ReturnType<typeof setInterval>
onMounted(() => {
  loadCharts()
  chartTimer = setInterval(loadCharts, 15000)
})
onUnmounted(() => clearInterval(chartTimer))
</script>

<style scoped>
.dashboard-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1400px;
}

.dash-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}
.page-title { font-size: 22px; font-weight: 700; color: var(--text-primary); }
.page-sub   { font-size: 12px; color: var(--text-tertiary); margin-top: 3px; }

.last-updated {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--bg-secondary);
  border: 1px solid var(--border-primary);
  border-radius: var(--radius-full);
  padding: 5px 12px;
}
.lu-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--success);
  animation: pulse-dot 2s ease-in-out infinite;
}
.lu-text { font-size: 11px; color: var(--text-tertiary); }

/* Skeleton */
.stats-skeleton {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}
.skeleton-card {
  height: 90px;
  border-radius: var(--radius-md);
  background: linear-gradient(90deg, var(--bg-secondary) 25%, var(--bg-tertiary) 50%, var(--bg-secondary) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

/* Seção workers */
.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}
.section-title {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--text-tertiary);
}
.section-link {
  font-size: 11px;
  color: var(--accent-primary);
  text-decoration: none;
}
.section-link:hover { text-decoration: underline; }

/* Charts + Feed row */
.charts-feed-row {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 16px;
  align-items: start;
}
.charts-col {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.panel {
  background: var(--bg-secondary);
  border: 1px solid var(--border-primary);
  border-radius: var(--radius-md);
  padding: 16px 18px;
}
.panel-feed {
  display: flex;
  flex-direction: column;
  height: 100%;
  max-height: 580px;
  padding: 0;
}
.panel-title {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--text-tertiary);
  margin-bottom: 14px;
}

.feed-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px 12px;
  border-bottom: 1px solid var(--border-secondary);
}
.feed-header .panel-title { margin-bottom: 0; }
.feed-count {
  font-size: 11px;
  color: var(--text-tertiary);
  font-family: var(--font-mono);
}
.feed-scroll {
  flex: 1;
  overflow-y: auto;
}

.queue-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}
.queue-panel-header .panel-title { margin-bottom: 0; }
.queue-count {
  font-size: 11px;
  color: var(--text-tertiary);
  font-family: var(--font-mono);
}
</style>
