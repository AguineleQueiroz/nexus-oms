<template>
  <aside class="sidebar">
    <div class="sidebar-logo">
      <span class="logo-mark">N</span>
      <div class="logo-text-group">
        <span class="logo-title">Nexus</span>
        <span class="logo-sub">OMS</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <router-link active-class="" class="nav-item" exact-active-class="active" to="/">
        <svg class="nav-icon" fill="none" viewBox="0 0 16 16">
          <rect fill="currentColor" height="6" opacity=".9" rx="1.5" width="6" x="1" y="1"/>
          <rect fill="currentColor" height="6" opacity=".5" rx="1.5" width="6" x="9" y="1"/>
          <rect fill="currentColor" height="6" opacity=".5" rx="1.5" width="6" x="1" y="9"/>
          <rect fill="currentColor" height="6" opacity=".5" rx="1.5" width="6" x="9" y="9"/>
        </svg>
        <span class="nav-label">Dashboard</span>
      </router-link>

      <router-link active-class="active" class="nav-item" to="/orders">
        <svg class="nav-icon" fill="none" viewBox="0 0 16 16">
          <rect height="12" rx="2" stroke="currentColor" stroke-width="1.5" width="12" x="2" y="2"/>
          <path d="M5 6h6M5 9h4" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"/>
        </svg>
        <span class="nav-label">Pedidos</span>
      </router-link>

      <router-link active-class="active" class="nav-item" to="/consumers">
        <svg class="nav-icon" fill="none" viewBox="0 0 16 16">
          <circle cx="8" cy="5" r="2.5" stroke="currentColor" stroke-width="1.5"/>
          <path d="M2.5 13c0-3.038 2.462-5.5 5.5-5.5s5.5 2.462 5.5 5.5" stroke="currentColor" stroke-linecap="round"
                stroke-width="1.5"/>
        </svg>
        <span class="nav-label">Workers</span>
      </router-link>

      <router-link active-class="active" class="nav-item" to="/events">
        <svg class="nav-icon" fill="none" viewBox="0 0 16 16">
          <path d="M8 2v4l2.5 1.5" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"/>
          <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/>
        </svg>
        <span class="nav-label">Eventos</span>
      </router-link>

      <router-link active-class="active" class="nav-item" to="/notifications">
        <svg class="nav-icon" fill="none" viewBox="0 0 16 16">
          <rect height="9" rx="1.5" stroke="currentColor" stroke-width="1.5" width="12" x="2" y="3"/>
          <path d="M2 5l6 4 6-4" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"/>
        </svg>
        <span class="nav-label">Notificações</span>
      </router-link>
    </nav>

    <div class="sidebar-footer">
      <div class="system-status">
        <span class="status-indicator"/>
        <span class="status-label">Sistema operacional</span>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.sidebar {
  width: var(--sidebar-width);
  background: var(--bg-sidebar);
  border-right: 1px solid var(--border-primary);
  display: flex;
  flex-direction: column;
  height: 100vh;
  position: sticky;
  top: 0;
  flex-shrink: 0;
}

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 18px 16px;
  border-bottom: 1px solid var(--border-secondary);
}

.logo-mark {
  width: 28px;
  height: 28px;
  background: var(--gradient-primary);
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}

.logo-text-group {
  display: flex;
  flex-direction: column;
  line-height: 1.1;
}

.logo-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--text-primary);
  letter-spacing: 0.02em;
}

.logo-sub {
  font-size: 10px;
  font-weight: 500;
  color: var(--text-tertiary);
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  padding: 12px 10px;
  gap: 2px;
  flex: 1;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 10px;
  border-radius: var(--radius-sm);
  color: var(--text-tertiary);
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  transition: background var(--transition-fast), color var(--transition-fast);
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.04);
  color: var(--text-secondary);
}

.nav-item.active {
  background: var(--accent-primary-light);
  color: var(--accent-primary);
}

.nav-icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.sidebar-footer {
  padding: 12px 16px;
  border-top: 1px solid var(--border-secondary);
}

.system-status {
  display: flex;
  align-items: center;
  gap: 7px;
}

.status-indicator {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--success);
  animation: pulse-dot 2s ease-in-out infinite;
}

.status-label {
  font-size: 11px;
  color: var(--text-tertiary);
}
</style>
