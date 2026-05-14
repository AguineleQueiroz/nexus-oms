<template>
  <div class="orders-view">
      <h1 class="page-title">Pedidos</h1>
      <div class="table-container">
        <table class="order-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Cliente</th>
              <th>
                <select
                  data-testid="status-filter"
                  v-model="filters.status"
                  class="filter-select"
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
              </th>
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
              @click="goToOrder(order.id)"
            >
              <td><code>{{ order.id }}</code></td>
              <td>{{ order.customer_name }}</td>
              <td><span :class="['status-chip', order.status]">{{ order.status }}</span></td>
              <td>{{ order.total }}</td>
              <td>{{ order.created_at }}</td>
            </tr>
          </tbody>
        </table>
        <div v-if="!loading && orders.length === 0" class="table-empty">Nenhum pedido encontrado.</div>
        <div v-if="loading" class="table-loading">Carregando…</div>
      </div>
      <div class="pagination">
        <button :disabled="meta.page <= 1" @click="filters.page = meta.page - 1">←</button>
        <span>{{ meta.page }} / {{ Math.ceil(meta.total / meta.per_page) || 1 }}</span>
        <button :disabled="meta.page * meta.per_page >= meta.total" @click="filters.page = meta.page + 1">→</button>
      </div>
    </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useOrders } from '@/composables/useOrders'

const router = useRouter()
const { orders, meta, filters, loading } = useOrders()

function goToOrder(id: string) {
  router.push(`/orders/${id}`)
}
</script>

<style scoped>
.orders-view { display: flex; flex-direction: column; gap: 16px; }
.page-title { font-size: 20px; font-weight: 600; }
.table-container {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}
.order-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.order-table th {
  padding: 10px 12px;
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--color-text-muted);
  background: var(--color-surface-2);
  border-bottom: 1px solid var(--color-border);
}
.order-row { cursor: pointer; }
.order-row:hover td { background: var(--color-surface-2); }
.order-row td { padding: 10px 12px; border-bottom: 1px solid var(--color-border); }
.filter-select {
  background: transparent;
  border: none;
  color: var(--color-text-muted);
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  cursor: pointer;
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
.status-chip.paid      { background: rgba(16,185,129,0.15); color: var(--color-green); }
.status-chip.cancelled { background: rgba(239,68,68,0.15);  color: var(--color-red); }
.table-empty, .table-loading {
  text-align: center;
  padding: 40px;
  color: var(--color-text-muted);
}
.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  font-size: 13px;
}
.pagination button {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  color: var(--color-text);
  padding: 4px 10px;
  border-radius: var(--radius-sm);
  cursor: pointer;
}
.pagination button:disabled { opacity: 0.3; cursor: not-allowed; }
</style>
