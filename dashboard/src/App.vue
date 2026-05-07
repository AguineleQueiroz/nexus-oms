<template>
  <div id="app">
    <router-view v-if="hasRouter" />
    <div v-else>Nexus OMS</div>
  </div>
</template>

<script setup lang="ts">
import { inject } from 'vue'

const hasRouter = !!inject('router', null)
</script>
