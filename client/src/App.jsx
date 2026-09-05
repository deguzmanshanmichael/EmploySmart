import { useEffect } from 'react'
import { AuthProvider } from './context/AuthContext'
import { NotificationProvider } from './context/NotificationContext'
import AppRoutes from './routes/AppRoutes'
import { settingsService } from './services/index'

function applyTheme(config) {
  const root = document.documentElement
  const primary = /^#[0-9a-f]{6}$/i.test(config?.landing_primary_color || '') ? config.landing_primary_color : '#1d4ed8'
  const accent = /^#[0-9a-f]{6}$/i.test(config?.landing_accent_color || '') ? config.landing_accent_color : '#d97706'
  root.style.setProperty('--color-primary', primary)
  root.style.setProperty('--color-primary-light', accent)
  root.style.setProperty('--color-primary-dark', primary)
}

export default function App() {
  useEffect(() => {
    const loadTheme = () => settingsService.getLanding().then((res) => applyTheme(res.data?.data)).catch(() => {})
    const handleThemeUpdate = () => loadTheme()
    loadTheme()
    window.addEventListener('employsmart-theme-updated', handleThemeUpdate)
    return () => window.removeEventListener('employsmart-theme-updated', handleThemeUpdate)
  }, [])

  return (
    <AuthProvider>
      <NotificationProvider>
        <AppRoutes />
      </NotificationProvider>
    </AuthProvider>
  )
}