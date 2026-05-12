<template>
  <aside class="sidebar">
    <div class="sidebar-logo">
      <span class="logo-text">Nexus OMS</span>
    </div>
    <nav class="sidebar-nav">
      <router-link to="/"          class="nav-item"><span class="nav-icon">⬛</span><span class="nav-label">Dashboard</span></router-link>
      <router-link to="/orders"    class="nav-item"><span class="nav-icon">📋</span><span class="nav-label">Pedidos</span></router-link>
      <router-link to="/consumers" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-label">Workers</span></router-link>
      <router-link to="/events"    class="nav-item"><span class="nav-icon">⚡</span><span class="nav-label">Eventos</span></router-link>
    </nav>
  </aside>
</template>

<style scoped>
.sidebar {
  width: var(--sidebar-width);
  background: var(--color-surface);
  border-right: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  height: 100vh;
  position: sticky;
  top: 0;
  flex-shrink: 0;
}
.sidebar-logo {
  padding: 20px 20px 16px;
  border-bottom: 1px solid var(--color-border);
}
.logo-text {
  font-weight: 600;
  font-size: 15px;
  color: var(--color-primary);
}
.sidebar-nav {
  display: flex;
  flex-direction: column;
  padding: 12px 8px;
  gap: 2px;
}
.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: var(--radius-sm);
  color: var(--color-text-muted);
  text-decoration: none;
  font-size: 13px;
  transition: background 0.15s, color 0.15s;
}
.nav-item:hover,
.nav-item.router-link-active {
  background: var(--color-surface-2);
  color: var(--color-text);
}
.nav-icon { font-size: 14px; }
</style>
