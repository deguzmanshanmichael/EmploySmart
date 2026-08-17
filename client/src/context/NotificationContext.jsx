import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import { useAuth } from './AuthContext'
import { notificationService } from '../services/index'

const NotificationContext = createContext({
  notifications: [],
  unreadCount: 0,
  addNotification: () => {},
  markAsRead: () => {},
  markAllAsRead: () => {},
})
const STORAGE_KEY = 'employsmart_notifications_v1'

const buildRoleNotifications = (role) => {
  const base = [
    {
      id: `${role}-1`,
      title: 'Important update',
      message: 'A high-priority action needs your attention.',
      priority: 'high',
      read: false,
      createdAt: new Date().toISOString(),
      category: 'important',
    },
    {
      id: `${role}-2`,
      title: 'Required follow-up',
      message: 'Please review the latest update so nothing is missed.',
      priority: 'normal',
      read: false,
      createdAt: new Date(Date.now() - 1000 * 60 * 15).toISOString(),
      category: 'needed',
    },
  ]

  if (role === 'jobseeker') {
    return [
      {
        id: `${role}-application`,
        title: 'Application update',
        message: 'Your application is being reviewed by the employer.',
        priority: 'high',
        read: false,
        createdAt: new Date().toISOString(),
        category: 'important',
      },
      {
        id: `${role}-profile`,
        title: 'Complete your profile',
        message: 'Add a resume and skills to improve your match rate.',
        priority: 'normal',
        read: false,
        createdAt: new Date(Date.now() - 1000 * 60 * 45).toISOString(),
        category: 'needed',
      },
      {
        id: `${role}-training`,
        title: 'Training reminder',
        message: 'A new training program is available for your career growth.',
        priority: 'normal',
        read: false,
        createdAt: new Date(Date.now() - 1000 * 60 * 90).toISOString(),
        category: 'needed',
      },
      ...base,
    ]
  }

  if (role === 'employer') {
    return [
      {
        id: `${role}-applicants`,
        title: 'New applicants received',
        message: 'You have new applicants waiting for your review.',
        priority: 'high',
        read: false,
        createdAt: new Date().toISOString(),
        category: 'important',
      },
      {
        id: `${role}-job-review`,
        title: 'Job post review',
        message: 'One of your job postings needs approval from PESO.',
        priority: 'high',
        read: false,
        createdAt: new Date(Date.now() - 1000 * 60 * 20).toISOString(),
        category: 'important',
      },
      {
        id: `${role}-company`,
        title: 'Company profile reminder',
        message: 'Complete your company profile to appear more credible to applicants.',
        priority: 'normal',
        read: false,
        createdAt: new Date(Date.now() - 1000 * 60 * 50).toISOString(),
        category: 'needed',
      },
      ...base,
    ]
  }

  if (role === 'peso') {
    return [
      {
        id: `${role}-jobs`,
        title: 'Pending job approvals',
        message: 'Several new job postings require your review today.',
        priority: 'high',
        read: false,
        createdAt: new Date().toISOString(),
        category: 'important',
      },
      {
        id: `${role}-verification`,
        title: 'Employer verification requests',
        message: 'New employer verification requests are waiting for action.',
        priority: 'high',
        read: false,
        createdAt: new Date(Date.now() - 1000 * 60 * 35).toISOString(),
        category: 'important',
      },
      {
        id: `${role}-reports`,
        title: 'Weekly report reminder',
        message: 'Review the latest report summaries before the end of the day.',
        priority: 'normal',
        read: false,
        createdAt: new Date(Date.now() - 1000 * 60 * 75).toISOString(),
        category: 'needed',
      },
      ...base,
    ]
  }

  if (role === 'clcdo') {
    return [
      {
        id: `${role}-enrollment`,
        title: 'Enrollment request',
        message: 'New training enrollments need confirmation.',
        priority: 'high',
        read: false,
        createdAt: new Date().toISOString(),
        category: 'important',
      },
      {
        id: `${role}-completion`,
        title: 'Completion review',
        message: 'Several training completions are pending your review.',
        priority: 'normal',
        read: false,
        createdAt: new Date(Date.now() - 1000 * 60 * 40).toISOString(),
        category: 'needed',
      },
      ...base,
    ]
  }

  if (role === 'admin') {
    return [
      {
        id: `${role}-staff`,
        title: 'Staff verification needed',
        message: 'A few staff accounts still require administrator review.',
        priority: 'high',
        read: false,
        createdAt: new Date().toISOString(),
        category: 'important',
      },
      {
        id: `${role}-system`,
        title: 'System activity alert',
        message: 'Review important system logs for suspicious activity.',
        priority: 'high',
        read: false,
        createdAt: new Date(Date.now() - 1000 * 60 * 25).toISOString(),
        category: 'important',
      },
      ...base,
    ]
  }

  return base
}

export function NotificationProvider({ children }) {
  const { user } = useAuth()
  const [notifications, setNotifications] = useState([])
  const [hydrated, setHydrated] = useState(false)

  useEffect(() => {
    const loadNotifications = async () => {
      if (!user?.role) {
        setNotifications([])
        setHydrated(true)
        return
      }

      try {
        const res = await notificationService.getAll()
        if (Array.isArray(res.data.data)) {
          const normalized = res.data.data.map((item) => ({
            ...item,
            read: item.read ?? item.is_read ?? false,
            createdAt: item.createdAt ?? item.created_at ?? new Date().toISOString(),
            role: user.role,
          }))
          setNotifications(normalized)
          setHydrated(true)
          return
        }
      } catch (err) {
        console.warn('Failed to load notifications from server:', err)
      }

      try {
        const raw = localStorage.getItem(STORAGE_KEY)
        if (raw) {
          const parsed = JSON.parse(raw)
          if (Array.isArray(parsed)) {
            const normalized = parsed.map((item) => ({
              ...item,
              read: item.read ?? item.is_read ?? false,
              createdAt: item.createdAt ?? item.created_at ?? new Date().toISOString(),
              role: item.role || user?.role || 'jobseeker',
            }))
            setNotifications(normalized)
            setHydrated(true)
            return
          }
        }
      } catch {
        // ignore and fall back to seeded notifications
      }

      setNotifications(buildRoleNotifications(user.role).map((item) => ({ ...item, role: user.role })))
      setHydrated(true)
    }

    loadNotifications()
  }, [user?.role])

  useEffect(() => {
    if (!hydrated) return
    localStorage.setItem(STORAGE_KEY, JSON.stringify(notifications))
  }, [notifications, hydrated])

  const roleNotifications = useMemo(() => {
    if (!user?.role) return []
    return notifications.filter((item) => item.role === user.role)
  }, [notifications, user?.role])

  const unreadCount = useMemo(() => roleNotifications.filter((item) => !item.read).length, [roleNotifications])

  const addNotification = async (notification) => {
    const item = {
      id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
      role: user?.role || 'jobseeker',
      read: false,
      createdAt: new Date().toISOString(),
      priority: 'normal',
      category: 'needed',
      ...notification,
    }
    setNotifications((prev) => [item, ...prev])

    if (user?.id) {
      try {
        const res = await notificationService.create({
          target_user_id: user.id,
          title: notification.title,
          message: notification.message,
          category: notification.category || 'needed',
          priority: notification.priority || 'normal',
        })
        if (res.data?.data) {
          const normalized = {
            ...res.data.data,
            read: res.data.data.read ?? res.data.data.is_read ?? false,
            createdAt: res.data.data.createdAt ?? res.data.data.created_at ?? new Date().toISOString(),
            role: user?.role || 'jobseeker',
          }
          setNotifications((prev) => [normalized, ...prev.filter((n) => n.id !== item.id)])
        }
      } catch (err) {
        console.warn('Failed to save notification to server:', err)
      }
    }
  }

  const markAsRead = (id) => {
    setNotifications((prev) => prev.map((item) => item.id === id ? { ...item, read: true } : item))
  }

  const markAllAsRead = () => {
    setNotifications((prev) => prev.map((item) => item.role === user?.role ? { ...item, read: true } : item))
  }

  const seedRoleNotifications = (role) => {
    const generated = buildRoleNotifications(role).map((item) => ({ ...item, role }))
    setNotifications((prev) => {
      const others = prev.filter((item) => item.role !== role)
      return [...others, ...generated]
    })
  }

  useEffect(() => {
    if (!user?.role || !hydrated) return
    const existing = notifications.some((item) => item.role === user.role)
    if (!existing) {
      seedRoleNotifications(user.role)
    }
  }, [user?.role, hydrated])

  return (
    <NotificationContext.Provider value={{ notifications: roleNotifications, unreadCount, addNotification, markAsRead, markAllAsRead }}>
      {children}
    </NotificationContext.Provider>
  )
}

export function useNotifications() {
  return useContext(NotificationContext)
}
