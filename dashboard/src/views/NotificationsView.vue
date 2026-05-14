<template>
  <div class="notif-view">
    <div class="view-header">
      <div>
        <h1 class="page-title">Notificações</h1>
        <p class="page-sub">Emails enviados pelo sistema via Mailpit</p>
      </div>
      <div class="header-right">
        <span class="total-badge">{{ total }} emails</span>
        <button class="refresh-btn" :class="{ spinning: loading }" @click="load">
          <svg viewBox="0 0 16 16" fill="none" width="14" height="14">
            <path d="M13.5 8A5.5 5.5 0 1 1 8 2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            <path d="M8 2.5 10.5 5 8 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>
    </div>

    <div class="table-card">
      <table class="notif-table">
        <thead>
          <tr>
            <th style="width:16px"></th>
            <th>Para</th>
            <th>Assunto</th>
            <th>Prévia</th>
            <th>Recebido em</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="m in messages"
            :key="m.id"
            class="notif-row"
            :class="{ unread: !m.read }"
          >
            <td>
              <span class="read-dot" :class="m.read ? 'read' : 'unread'" />
            </td>
            <td class="to-cell">{{ m.to }}</td>
            <td class="subject-cell" :class="{ bold: !m.read }">{{ m.subject }}</td>
            <td class="snippet-cell">{{ m.snippet }}</td>
            <td class="date-cell mono">{{ formatDate(m.created) }}</td>
            <td>
              <button class="view-btn" @click="openMessage(m.id)">Ver</button>
            </td>
          </tr>
          <tr v-if="messages.length === 0 && !loading">
            <td colspan="6" class="empty-row">
              {{ error ? 'Erro ao carregar emails.' : 'Nenhum email ainda. Crie pedidos com --live para gerar notificações.' }}
            </td>
          </tr>
          <tr v-if="loading && messages.length === 0">
            <td colspan="6" class="empty-row">Carregando...</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="modal.open" class="modal-backdrop" @click.self="modal.open = false">
        <div class="modal-box">
          <div class="modal-header">
            <div class="modal-meta">
              <span class="modal-subject">{{ modal.subject }}</span>
              <span class="modal-to">Para: {{ modal.to }}</span>
            </div>
            <button class="modal-close" @click="modal.open = false">✕</button>
          </div>
          <div class="modal-date mono">{{ formatDate(modal.created) }}</div>
          <pre v-if="!modal.loading" class="modal-body">{{ modal.text }}</pre>
          <div v-else class="modal-loading">Carregando...</div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/services/api'
import type { MailNotification } from '@/types'

const messages = ref<MailNotification[]>([])
const total    = ref(0)
const loading  = ref(false)
const error    = ref(false)

const modal = ref({
  open: false, loading: false,
  subject: '', to: '', created: '', text: '',
})

async function load() {
  loading.value = true
  error.value   = false
  try {
    const data      = await api.fetchNotifications(100)
    messages.value  = data.messages
    total.value     = data.total
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

async function openMessage(id: string) {
  modal.value = { open: true, loading: true, subject: '', to: '', created: '', text: '' }
  try {
    const m = await api.fetchNotification(id)
    modal.value = { open: true, loading: false, subject: m.subject, to: m.to, created: m.created, text: m.text }
  } catch {
    modal.value.loading = false
    modal.value.text    = 'Erro ao carregar mensagem.'
  }
}

function formatDate(iso: string): string {
  return iso ? new Date(iso).toLocaleString('pt-BR') : '—'
}

onMounted(load)
</script>

<style scoped>
.notif-view {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.view-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}
.page-title { font-size: 20px; font-weight: 600; color: var(--text-primary); }
.page-sub   { font-size: 12px; color: var(--text-tertiary); margin-top: 3px; }

.header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}
.total-badge {
  font-size: 11px;
  font-family: var(--font-mono);
  color: var(--text-tertiary);
  background: var(--bg-secondary);
  border: 1px solid var(--border-primary);
  padding: 4px 10px;
  border-radius: var(--radius-full);
}
.refresh-btn {
  background: var(--bg-secondary);
  border: 1px solid var(--border-primary);
  border-radius: var(--radius-sm);
  color: var(--text-tertiary);
  cursor: pointer;
  padding: 5px 7px;
  display: flex;
  align-items: center;
  transition: color 150ms;
}
.refresh-btn:hover { color: var(--accent-primary); }
.refresh-btn.spinning svg { animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.table-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-primary);
  border-radius: var(--radius-md);
  overflow: auto;
}

.notif-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.notif-table th {
  text-align: left;
  padding: 10px 12px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-tertiary);
  border-bottom: 1px solid var(--border-primary);
  white-space: nowrap;
}

.notif-table td {
  padding: 8px 12px;
  border-bottom: 1px solid var(--border-secondary);
  color: var(--text-secondary);
}

.notif-row:last-child td { border-bottom: none; }
.notif-row:hover td { background: rgba(255,255,255,0.02); }

.read-dot {
  display: inline-block;
  width: 6px; height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}
.read-dot.unread { background: var(--accent-primary); }
.read-dot.read   { background: var(--border-primary); }

.to-cell      { color: var(--text-primary); font-family: var(--font-mono); font-size: 11px; }
.subject-cell { color: var(--text-secondary); max-width: 200px; }
.subject-cell.bold { color: var(--text-primary); font-weight: 600; }
.snippet-cell { color: var(--text-tertiary); max-width: 340px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.date-cell    { color: var(--text-tertiary); white-space: nowrap; }
.mono         { font-family: var(--font-mono); }

.view-btn {
  background: none;
  border: 1px solid var(--border-primary);
  border-radius: var(--radius-sm);
  color: var(--text-tertiary);
  font-size: 11px;
  padding: 2px 10px;
  cursor: pointer;
  transition: color 150ms, border-color 150ms;
}
.view-btn:hover { color: var(--accent-primary); border-color: var(--accent-primary); }

.empty-row { text-align: center; color: var(--text-tertiary); padding: 32px 12px; font-size: 12px; }

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.modal-box {
  background: var(--bg-secondary);
  border: 1px solid var(--border-primary);
  border-radius: var(--radius-md);
  width: min(600px, 90vw);
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 14px 16px 10px;
  border-bottom: 1px solid var(--border-primary);
  gap: 12px;
}
.modal-meta    { display: flex; flex-direction: column; gap: 3px; }
.modal-subject { font-size: 14px; font-weight: 600; color: var(--text-primary); }
.modal-to      { font-size: 11px; color: var(--text-tertiary); font-family: var(--font-mono); }
.modal-close {
  background: none; border: none;
  color: var(--text-tertiary); cursor: pointer;
  font-size: 14px; padding: 2px 6px; flex-shrink: 0;
}
.modal-close:hover { color: var(--text-primary); }
.modal-date {
  font-size: 11px;
  color: var(--text-tertiary);
  padding: 6px 16px;
  border-bottom: 1px solid var(--border-secondary);
}
.modal-body {
  padding: 16px;
  overflow: auto;
  font-family: var(--font-mono);
  font-size: 12px;
  line-height: 1.7;
  color: var(--text-primary);
  margin: 0;
  white-space: pre-wrap;
}
.modal-loading {
  padding: 32px;
  text-align: center;
  color: var(--text-tertiary);
  font-size: 12px;
}
</style>
