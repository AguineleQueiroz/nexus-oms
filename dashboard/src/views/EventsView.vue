<template>
  <div class="events-view">
    <div class="view-header">
      <h1 class="page-title">Eventos</h1>
      <select
          v-model="typeFilter"
          class="type-filter"
          data-testid="type-filter"
      >
        <option value="">Todos os tipos</option>
        <option
            v-for="et in eventTypes"
            :key="et.event_type"
            :value="et.event_type"
        >
          {{ et.event_type }} ({{ et.count }})
        </option>
      </select>
    </div>

    <div class="table-card">
      <table class="events-table">
        <thead>
        <tr>
          <th>Tipo</th>
          <th>Order ID</th>
          <th>Routing Key</th>
          <th>Worker</th>
          <th>Status</th>
          <th>Publicado em</th>
          <th>Payload</th>
        </tr>
        </thead>
        <tbody>
        <tr
            v-for="event in filtered"
            :key="event.id"
            class="event-row"
            data-testid="event-row"
        >
          <td>
                <span :class="['type-badge', typeBadgeClass(event.event_type)]">
                  {{ event.event_type }}
                </span>
          </td>
          <td class="mono dimmed">{{ event.order_id.slice(0, 8) }}…</td>
          <td class="mono">{{ event.routing_key }}</td>
          <td class="dimmed">{{ event.worker_id ?? '—' }}</td>
          <td>
            <span :class="['status-dot', event.processed ? 'done' : 'pending']"/>
          </td>
          <td class="mono dimmed">{{ formatDate(event.published_at) }}</td>
          <td>
            <button
                class="payload-btn"
                data-testid="open-payload"
                @click="openPayload(event)"
            >
              { … }
            </button>
          </td>
        </tr>
        <tr v-if="filtered.length === 0">
          <td class="empty-row" colspan="7">Nenhum evento encontrado.</td>
        </tr>
        </tbody>
      </table>
    </div>

    <div class="flow-card">
      <h2 class="flow-title">Fluxo de Eventos</h2>
      <EventFlowChart :events="events"/>
    </div>

    <PayloadModal
        v-model="showModal"
        :event-type="selectedEventType"
        :payload="selectedPayload"
    />
  </div>
</template>

<script lang="ts" setup>
import {computed, onMounted, ref} from 'vue'
import PayloadModal from '@/components/PayloadModal.vue'
import EventFlowChart from '@/components/charts/EventFlowChart.vue'
import {api} from '@/services/api'
import type {EventTypeCount, OrderEvent} from '@/types'

const events = ref<OrderEvent[]>([])
const eventTypes = ref<EventTypeCount[]>([])
const typeFilter = ref('')
const showModal = ref(false)
const selectedPayload = ref<Record<string, unknown>>({})
const selectedEventType = ref('')

onMounted(async () => {
  const [feed, byType] = await Promise.all([
    api.fetchEventFeed(200).catch(() => [] as OrderEvent[]),
    api.fetchEventsByType().catch(() => [] as EventTypeCount[]),
  ])
  events.value = feed
  eventTypes.value = byType
})

const filtered = computed(() =>
    typeFilter.value
        ? events.value.filter(e => e.event_type === typeFilter.value)
        : events.value
)

function openPayload(event: OrderEvent) {
  selectedPayload.value = event.payload
  selectedEventType.value = event.event_type
  showModal.value = true
}

function formatDate(iso: string): string {
  return iso ? new Date(iso).toLocaleString('pt-BR') : '—'
}

function typeBadgeClass(eventType: string): string {
  if (eventType.includes('approved') || eventType === 'order.delivered') return 'success'
  if (eventType.includes('refused') || eventType === 'order.cancelled') return 'danger'
  if (eventType.includes('payment')) return 'warning'
  return 'info'
}
</script>

<style scoped>
.events-view {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.view-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-title {
  font-size: 20px;
  font-weight: 600;
}

.type-filter {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  color: var(--color-text);
  font-size: 13px;
  padding: 6px 10px;
  min-width: 220px;
}

.table-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: auto;
}

.events-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.events-table th {
  text-align: left;
  padding: 10px 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-muted);
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.events-table td {
  padding: 8px 12px;
  border-bottom: 1px solid var(--color-border);
}

.event-row:last-child td {
  border-bottom: none;
}

.event-row:hover td {
  background: var(--color-surface-2, rgba(255, 255, 255, 0.03));
}

.mono {
  font-family: var(--font-mono);
}

.dimmed {
  color: var(--color-text-muted);
}

.type-badge {
  display: inline-block;
  padding: 2px 7px;
  border-radius: 99px;
  font-family: var(--font-mono);
  font-size: 11px;
  white-space: nowrap;
}

.type-badge.success {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.type-badge.danger {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}

.type-badge.warning {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
}

.type-badge.info {
  background: rgba(59, 130, 246, 0.12);
  color: #3b82f6;
}

.status-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.status-dot.done {
  background: #10b981;
}

.status-dot.pending {
  background: #f59e0b;
}

.payload-btn {
  background: none;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  color: var(--color-text-muted);
  font-family: var(--font-mono);
  font-size: 11px;
  padding: 2px 8px;
  cursor: pointer;
  transition: color 150ms, border-color 150ms;
}

.payload-btn:hover {
  color: var(--color-amber);
  border-color: var(--color-amber);
}

.empty-row {
  text-align: center;
  color: var(--color-text-muted);
  padding: 32px;
}

.flow-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
}

.flow-title {
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-muted);
  margin-bottom: 12px;
}
</style>
