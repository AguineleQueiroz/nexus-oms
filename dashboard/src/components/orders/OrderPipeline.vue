<template>
  <div class="order-pipeline" data-testid="order-pipeline">
    <template v-for="(step, i) in pipeline" :key="step.status">
      <StateNode
          :data-testid="`node-${step.status}`"
          :label="step.label"
          :pipeline-status="step.status"
          :status="nodeStatus(step.status)"
      />
      <div
          v-if="i < pipeline.length - 1"
          :class="['pipeline-connector', isCompleted(step.status) ? 'done' : '']"
      />
    </template>
  </div>
</template>

<script lang="ts" setup>
import StateNode from './StateNode.vue'

const props = defineProps<{ currentStatus: string }>()

const pipeline = [
  {status: 'created', label: 'Criado'},
  {status: 'payment_pending', label: 'Pgto Pendente'},
  {status: 'paid', label: 'Pago'},
  {status: 'picking', label: 'Separando'},
  {status: 'shipped', label: 'Enviado'},
  {status: 'delivered', label: 'Entregue'},
]

const order = pipeline.map(s => s.status)

function isCompleted(status: string): boolean {
  const current = order.indexOf(props.currentStatus)
  const idx = order.indexOf(status)
  return idx !== -1 && current !== -1 && idx < current
}

function nodeStatus(status: string): 'completed' | 'active' | 'pending' | 'cancelled' {
  if (props.currentStatus === 'cancelled' && status === 'cancelled') return 'cancelled'
  if (props.currentStatus === 'payment_refused' && status === 'payment_pending') return 'cancelled'
  if (status === props.currentStatus) return 'active'
  if (isCompleted(status)) return 'completed'
  return 'pending'
}
</script>

<style scoped>
.order-pipeline {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
  row-gap: 12px;
}

.pipeline-connector {
  flex: 1;
  min-width: 20px;
  max-width: 48px;
  height: 2px;
  background: #3f3f46;
  border-radius: 1px;
  transition: background 300ms;
}

.pipeline-connector.done {
  background: #10b981;
}
</style>
