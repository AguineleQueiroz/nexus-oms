<template>
  <Teleport to="body">
    <div
        v-if="modelValue"
        class="modal-backdrop"
        data-testid="payload-modal"
        @click.self="$emit('update:modelValue', false)"
    >
      <div class="modal-box">
        <div class="modal-header">
          <span class="modal-title">Payload — <code>{{ eventType }}</code></span>
          <button class="modal-close" @click="$emit('update:modelValue', false)">✕</button>
        </div>
        <pre class="modal-body" v-html="highlighted"/>
      </div>
    </div>
  </Teleport>
</template>

<script lang="ts" setup>
import {computed} from 'vue'

const props = defineProps<{
  modelValue: boolean
  payload: Record<string, unknown>
  eventType: string
}>()

defineEmits<{ 'update:modelValue': [value: boolean] }>()

function escape(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

const highlighted = computed(() => {
  const json = JSON.stringify(props.payload, null, 2)
  return escape(json)
      .replace(/("[\w_]+")\s*:/g, '<span class="key">$1</span>:')
      .replace(/:\s*(".*?")/g, ': <span class="str">$1</span>')
      .replace(/:\s*(\d+\.?\d*)/g, ': <span class="num">$1</span>')
      .replace(/:\s*(true|false|null)/g, ': <span class="kw">$1</span>')
})
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-box {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  width: min(680px, 90vw);
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid var(--color-border);
}

.modal-title {
  font-size: 13px;
  font-weight: 600;
}

.modal-title code {
  font-family: var(--font-mono);
  color: var(--color-amber);
  font-size: 12px;
}

.modal-close {
  background: none;
  border: none;
  color: var(--color-text-muted);
  cursor: pointer;
  font-size: 14px;
  padding: 4px 8px;
}

.modal-close:hover {
  color: var(--color-text);
}

.modal-body {
  padding: 16px;
  overflow: auto;
  font-family: var(--font-mono);
  font-size: 12px;
  line-height: 1.6;
  color: var(--color-text);
  margin: 0;
}

:deep(.key) {
  color: var(--color-amber);
}

:deep(.str) {
  color: #a3e635;
}

:deep(.num) {
  color: #60a5fa;
}

:deep(.kw) {
  color: #c084fc;
}
</style>
