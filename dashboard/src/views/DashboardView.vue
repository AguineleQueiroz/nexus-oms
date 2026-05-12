<template>
  <AppLayout>
    <div class="dashboard-view">
      <h1 class="page-title">Dashboard</h1>
      <StatsBar v-if="stats" :stats="stats" />
      <div class="charts-row">
        <div class="chart-card">
          <h2 class="card-title">Throughput (60 min)</h2>
          <ThroughputChart :data="throughput" />
        </div>
        <div class="chart-card">
          <h2 class="card-title">Funil de Status</h2>
          <FunnelChart :data="funnel" />
        </div>
      </div>
      <div class="feed-card">
        <h2 class="card-title">Event Feed</h2>
        <EventFeed :events="events" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue'
import StatsBar from '@/components/StatsBar.vue'
import ThroughputChart from '@/components/charts/ThroughputChart.vue'
import FunnelChart from '@/components/charts/FunnelChart.vue'
import EventFeed from '@/components/EventFeed.vue'
import { useStats } from '@/composables/useStats'
import { useEventFeed } from '@/composables/useEventFeed'
import { ref } from 'vue'
import type { ThroughputPoint, FunnelItem } from '@/types'
import { api } from '@/services/api'

const { stats } = useStats(5000)
const { events } = useEventFeed(2000)
const throughput = ref<ThroughputPoint[]>([])
const funnel     = ref<FunnelItem[]>([])

api.fetchThroughput().then(d => (throughput.value = d)).catch(() => {})
api.fetchFunnel().then(d => (funnel.value = d)).catch(() => {})
</script>

<style scoped>
.dashboard-view { display: flex; flex-direction: column; gap: 24px; }
.page-title { font-size: 20px; font-weight: 600; }
.charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.chart-card, .feed-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
}
.card-title { font-size: 13px; font-weight: 600; margin-bottom: 16px; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
</style>
