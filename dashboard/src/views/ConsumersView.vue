<template>
  <div class="consumers-view">
    <h1 class="page-title">Workers</h1>
    <ConsumerGrid :consumers="consumers" />
  </div>
</template>

<script setup lang="ts">
import ConsumerGrid from '@/components/consumers/ConsumerGrid.vue'
import { useConsumers } from '@/composables/useConsumers'

const { consumers } = useConsumers(5000)
</script>

<style scoped>
.consumers-view { display: flex; flex-direction: column; gap: 20px; }
.page-title { font-size: 20px; font-weight: 600; }
</style>
