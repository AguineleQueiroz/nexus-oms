<template>
  <div class="workers-mini">
    <div v-if="consumers.length === 0" class="workers-empty">
      Nenhum worker registrado
    </div>
    <div
        v-for="w in consumers"
        :key="w.worker_id"
        :class="['worker-card', w.status]"
        data-testid="worker-mini-card"
    >
      <div class="wc-top">
        <span :class="['wc-dot', w.status]"/>
        <span class="wc-type">{{ shortType(w.worker_type) }}</span>
        <span :class="['wc-badge', w.status]">{{ w.status }}</span>
      </div>
      <div class="wc-queue">{{ w.queue_name }}</div>
      <div class="wc-counters">
        <div class="counter">
          <span class="counter-value success">{{ w.events_processed.toLocaleString('pt-BR') }}</span>
          <span class="counter-label">processados</span>
        </div>
        <div class="counter-divider"/>
        <div class="counter">
          <span :class="['counter-value', w.events_failed > 0 ? 'error' : 'muted']">{{ w.events_failed }}</span>
          <span class="counter-label">falhas</span>
        </div>
      </div>
      <div class="wc-heartbeat">
        <span class="heartbeat-dot"/>
        <span class="heartbeat-label">{{ heartbeatAgo(w.last_heartbeat) }}</span>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import type {Consumer} from '@/types'

defineProps<{ consumers: Consumer[] }>()

function shortType(workerType: string): string {
  return workerType.replace('Worker', '')
}

function heartbeatAgo(iso: string): string {
  const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (diff < 60) return `${diff}s atrás`
  return `${Math.floor(diff / 60)}m atrás`
}
</script>

<style scoped>
.workers-mini {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 10px;
}

.workers-empty {
  color: var(--text-tertiary);
  font-size: 13px;
  padding: 20px;
  text-align: center;
  grid-column: 1 / -1;
}

.worker-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-primary);
  border-radius: var(--radius-md);
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  transition: border-color var(--transition-fast);
}

.worker-card.active {
  border-color: rgba(0, 230, 118, 0.2);
}

.worker-card.idle {
  border-color: rgba(255, 214, 0, 0.15);
}

.worker-card.stopped {
  border-color: rgba(255, 61, 0, 0.15);
}

.wc-top {
  display: flex;
  align-items: center;
  gap: 7px;
}

.wc-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}

.wc-dot.active {
  background: var(--success);
  animation: pulse-dot 2s ease-in-out infinite;
}

.wc-dot.idle {
  background: var(--warning);
}

.wc-dot.stopped {
  background: var(--error);
}

.wc-type {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-primary);
  flex: 1;
}

.wc-badge {
  font-size: 9px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 2px 6px;
  border-radius: var(--radius-full);
}

.wc-badge.active {
  background: var(--success-bg);
  color: var(--success);
}

.wc-badge.idle {
  background: var(--warning-bg);
  color: var(--warning);
}

.wc-badge.stopped {
  background: var(--error-bg);
  color: var(--error);
}

.wc-queue {
  font-family: var(--font-mono);
  font-size: 10px;
  color: var(--text-tertiary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.wc-counters {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--bg-tertiary);
  border-radius: var(--radius-sm);
  padding: 6px 10px;
}

.counter {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1px;
  flex: 1;
}

.counter-value {
  font-family: var(--font-mono);
  font-size: 15px;
  font-weight: 700;
  line-height: 1;
}

.counter-value.success {
  color: var(--success);
}

.counter-value.error {
  color: var(--error);
}

.counter-value.muted {
  color: var(--text-tertiary);
}

.counter-label {
  font-size: 9px;
  color: var(--text-tertiary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.counter-divider {
  width: 1px;
  height: 24px;
  background: var(--border-primary);
}

.wc-heartbeat {
  display: flex;
  align-items: center;
  gap: 5px;
}

.heartbeat-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--text-tertiary);
}

.heartbeat-label {
  font-size: 10px;
  color: var(--text-tertiary);
}
</style>
