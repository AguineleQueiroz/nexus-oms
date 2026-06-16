<template>
  <div :class="['state-node', status]" :data-status="pipelineStatus ?? status">
    <div class="node-dot"/>
    <span class="node-label">{{ label }}</span>
  </div>
</template>

<script lang="ts" setup>
defineProps<{
  label: string
  status: 'completed' | 'active' | 'pending' | 'cancelled'
  pipelineStatus?: string
}>()
</script>

<style scoped>
.state-node {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  min-width: 72px;
}

.node-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  border: 2px solid var(--node-color, #3f3f46);
  background: transparent;
  transition: all 300ms;
}

.state-node.completed .node-dot {
  background: var(--node-color, #10b981);
  border-color: var(--node-color, #10b981);
}

.state-node.active .node-dot {
  background: var(--color-amber, #f59e0b);
  border-color: var(--color-amber, #f59e0b);
  box-shadow: 0 0 8px rgba(245, 158, 11, 0.5);
}

.state-node.cancelled .node-dot {
  background: #ef4444;
  border-color: #ef4444;
}

.state-node.pending .node-dot {
  border-color: #3f3f46;
}

.node-label {
  font-size: 10px;
  color: var(--color-text-muted, #71717a);
  text-align: center;
  white-space: nowrap;
  transition: color 300ms;
}

.state-node.active .node-label {
  color: var(--color-amber, #f59e0b);
  font-weight: 600;
}

.state-node.completed .node-label {
  color: #10b981;
}

.state-node.cancelled .node-label {
  color: #ef4444;
}
</style>
