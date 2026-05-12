<template>
  <AppLayout>
    <div class="events-view">
      <h1 class="page-title">Event Feed</h1>
      <div class="feed-container">
        <EventFeed :events="events" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue'
import EventFeed from '@/components/EventFeed.vue'
import { useEventFeed } from '@/composables/useEventFeed'

const { events } = useEventFeed(2000)
</script>

<style scoped>
.events-view { display: flex; flex-direction: column; gap: 20px; }
.page-title { font-size: 20px; font-weight: 600; }
.feed-container {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 16px;
}
</style>
