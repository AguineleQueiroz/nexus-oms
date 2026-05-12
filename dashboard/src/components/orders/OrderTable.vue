<template>
  <div class="order-table-wrapper">
    <div class="table-controls">
      <select
        data-testid="status-filter"
        :value="filters.status"
        class="filter-select"
        @change="emit('filter', ($event.target as HTMLSelectElement).value)"
      >
        <option value="">Todos os status</option>
        <option value="created">Criado</option>
        <option value="payment_pending">Aguard. Pgto</option>
        <option value="paid">Pago</option>
        <option value="preparing">Preparando</option>
        <option value="ready">Pronto</option>
        <option value="in_transit">Em Trânsito</option>
        <option value="delivered">Entregue</option>
        <option value="cancelled">Cancelado</option>
      </select>
    </div>
    <table class="order-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Cliente</th>
          <th>Status</th>
          <th>Total</th>
          <th>Criado em</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="order in orders"
          :key="order.id"
          data-testid="order-row"
          class="order-row"
          @click="emit('select', order.id)"
        >
          <td><code>{{ order.id.slice(0, 8) }}</code></td>
          <td>{{ order.customer_name }}</td>
          <td><span :class="['status-chip', order.status]">{{ order.status }}</span></td>
          <td>R$ {{ (order.total / 100).toFixed(2) }}</td>
          <td>{{ formatDate(order.created_at) }}</td>
        </tr>
      </tbody>
    </table>
    <div v-if="orders.length === 0" class="table-empty">Nenhum pedido encontrado.</div>
    <div class="table-pagination">
      <span class="pagination-info">{{ meta.total }} pedidos</span>
      <div class="pagination-controls">
        <button :disabled="meta.page <= 1" @click="emit('page', meta.page - 1)">←</button>
        <span>{{ meta.page }}</span>
        <button :disabled="meta.page * meta.per_page >= meta.total" @click="emit('page', meta.page + 1)">→</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Order } from '@/types'

defineProps<{
  orders: Order[]
  meta: { page: number; per_page: number; total: number }
  filters: { status: string }
}>()

const emit = defineEmits<{
  select: [id: string]
  filter: [status: string]
  page:   [page: number]
}>()

function formatDate(iso: string): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('pt-BR')
}
</script>

<style scoped>
.order-table-wrapper { display: flex; flex-direction: column; gap: 12px; }
.table-controls { display: flex; gap: 8px; }
.filter-select {
  padding: 6px 10px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  color: var(--color-text);
  font-size: 13px;
}
.order-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.order-table th {
  padding: 8px 12px;
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--color-text-muted);
  border-bottom: 1px solid var(--color-border);
}
.order-row {
  cursor: pointer;
  transition: background 0.1s;
}
.order-row:hover td { background: var(--color-surface-2); }
.order-row td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--color-border);
}
.status-chip {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  padding: 2px 8px;
  border-radius: 99px;
  background: var(--color-surface-2);
  color: var(--color-text-muted);
}
.status-chip.paid       { background: rgba(16,185,129,0.15); color: var(--color-green); }
.status-chip.delivered  { background: rgba(99,102,241,0.15); color: var(--color-primary); }
.status-chip.cancelled  { background: rgba(239,68,68,0.15);  color: var(--color-red); }
.table-empty { text-align: center; padding: 40px; color: var(--color-text-muted); }
.table-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: var(--color-text-muted);
}
.pagination-controls { display: flex; align-items: center; gap: 12px; }
.pagination-controls button {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  color: var(--color-text);
  padding: 4px 10px;
  border-radius: var(--radius-sm);
  cursor: pointer;
}
.pagination-controls button:disabled { opacity: 0.3; cursor: not-allowed; }
</style>
