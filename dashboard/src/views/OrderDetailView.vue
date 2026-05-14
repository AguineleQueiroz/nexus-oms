<template>
  <div class="order-detail-view">
      <div class="detail-header">
        <button class="back-btn" @click="router.back()">← Voltar</button>
        <h1 class="page-title" v-if="order">{{ order.customer_name }}</h1>
        <span v-if="order" :class="['status-chip', order.status]">{{ order.status }}</span>
      </div>
      <div v-if="loading" class="loading">Carregando…</div>
      <div v-else-if="order" class="detail-body">
        <OrderPipeline :current-status="order.status" class="pipeline-section" />
        <div class="info-card">
          <div class="info-row"><span>ID</span><code>{{ order.id }}</code></div>
          <div class="info-row"><span>Email</span><span>{{ order.customer_email }}</span></div>
          <div class="info-row"><span>Total</span><span>R$ {{ (order.total / 100).toFixed(2) }}</span></div>
          <div class="info-row"><span>Criado</span><span>{{ formatDate(order.created_at) }}</span></div>
        </div>
        <div class="actions">
          <button v-if="canPay"     class="btn-primary"  @click="pay">Pagar</button>
          <button v-if="canAdvance" class="btn-secondary" @click="advance">Avançar</button>
          <button v-if="canCancel"  class="btn-danger"   @click="cancel">Cancelar</button>
        </div>
        <div class="timeline-section">
          <h2 class="section-title">Histórico de Eventos</h2>
          <OrderTimeline :events="order.events" />
        </div>
      </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import OrderTimeline from '@/components/orders/OrderTimeline.vue'
import OrderPipeline from '@/components/orders/OrderPipeline.vue'
import { api } from '@/services/api'
import type { OrderDetail } from '@/types'

const route  = useRoute()
const router = useRouter()
const order  = ref<OrderDetail | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    order.value = await api.fetchOrder(route.params.id as string)
  } finally {
    loading.value = false
  }
})

const canPay     = computed(() => order.value?.status === 'payment_pending')
const canAdvance = computed(() => ['paid', 'preparing', 'ready'].includes(order.value?.status ?? ''))
const canCancel  = computed(() => !['delivered', 'cancelled'].includes(order.value?.status ?? ''))

async function pay()     { order.value = await api.payOrder(route.params.id as string) }
async function advance() { order.value = await api.advanceOrder(route.params.id as string) }
async function cancel()  { order.value = await api.cancelOrder(route.params.id as string) }

function formatDate(iso: string) { return iso ? new Date(iso).toLocaleString('pt-BR') : '—' }
</script>

<style scoped>
.order-detail-view { display: flex; flex-direction: column; gap: 20px; max-width: 800px; }
.detail-header { display: flex; align-items: center; gap: 12px; }
.back-btn { background: none; border: none; color: var(--color-text-muted); cursor: pointer; font-size: 13px; }
.page-title { font-size: 20px; font-weight: 600; flex: 1; }
.status-chip { font-size: 11px; font-weight: 600; text-transform: uppercase; padding: 3px 10px; border-radius: 99px; background: var(--color-surface-2); color: var(--color-text-muted); }
.loading { color: var(--color-text-muted); padding: 40px; text-align: center; }
.info-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.info-row { display: flex; justify-content: space-between; font-size: 13px; }
.info-row span:first-child { color: var(--color-text-muted); }
.actions { display: flex; gap: 8px; }
.btn-primary, .btn-secondary, .btn-danger {
  padding: 8px 16px; border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; cursor: pointer;
}
.btn-primary   { background: var(--color-primary); color: #fff; }
.btn-secondary { background: var(--color-surface-2); color: var(--color-text); }
.btn-danger    { background: rgba(239,68,68,0.15); color: var(--color-red); }
.section-title { font-size: 13px; font-weight: 600; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 12px; }
</style>
