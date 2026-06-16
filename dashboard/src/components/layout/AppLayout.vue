<template>
  <div class="app-layout">
    <Sidebar/>
    <main class="main-content">
      <slot/>
    </main>
  </div>
</template>

<script lang="ts" setup>
import Sidebar from './Sidebar.vue'
</script>

<style scoped>
.app-layout {
  display: flex;
  min-height: 100vh;
}

.main-content {
  flex: 1;
  padding: 24px;
  overflow-y: auto;
}
</style>
