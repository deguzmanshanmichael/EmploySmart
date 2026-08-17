import { useMemo } from 'react'
import { Link } from 'react-router-dom'
import { FiBell, FiCheckCircle, FiAlertCircle, FiArrowLeft } from 'react-icons/fi'
import { useNotifications } from '../../context/NotificationContext'

export default function NotificationsPage() {
  const { notifications, unreadCount, markAsRead, markAllAsRead } = useNotifications()

  const sorted = useMemo(() => [...notifications].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)), [notifications])

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <div className="page-header flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <p className="text-sm font-semibold text-blue-600 uppercase tracking-[0.2em]">Alerts</p>
          <h1 className="text-2xl font-bold text-gray-900">Notifications</h1>
          <p className="text-sm text-gray-600">Important updates, required follow-ups, and account-specific reminders.</p>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={markAllAsRead}
            className="px-4 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50"
          >
            Mark all read
          </button>
          <Link to=".." className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-900 text-white text-sm font-semibold">
            <FiArrowLeft size={16} /> Back
          </Link>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-red-50 p-2 text-red-600"><FiBell size={18} /></div>
            <div>
              <p className="text-sm text-gray-500">Unread</p>
              <p className="text-2xl font-bold text-gray-900">{unreadCount}</p>
            </div>
          </div>
        </div>
        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-amber-50 p-2 text-amber-600"><FiAlertCircle size={18} /></div>
            <div>
              <p className="text-sm text-gray-500">Priority</p>
              <p className="text-2xl font-bold text-gray-900">{sorted.filter((item) => item.priority === 'high').length}</p>
            </div>
          </div>
        </div>
        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-green-50 p-2 text-green-600"><FiCheckCircle size={18} /></div>
            <div>
              <p className="text-sm text-gray-500">Completed</p>
              <p className="text-2xl font-bold text-gray-900">{sorted.filter((item) => item.read).length}</p>
            </div>
          </div>
        </div>
      </div>

      <div className="space-y-3">
        {sorted.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
            No notifications yet.
          </div>
        ) : sorted.map((item) => (
          <div key={item.id} className={`rounded-2xl border p-5 shadow-sm ${item.read ? 'bg-white border-gray-200' : 'bg-red-50 border-red-200'}`}>
            <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
              <div className="flex gap-3">
                <div className={`rounded-xl p-2 ${item.priority === 'high' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600'}`}>
                  <FiBell size={18} />
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <h3 className="font-semibold text-gray-900">{item.title}</h3>
                    {!item.read && <span className="rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">New</span>}
                  </div>
                  <p className="mt-1 text-sm text-gray-600">{item.message}</p>
                  <p className="mt-2 text-xs text-gray-500">{new Date(item.createdAt).toLocaleString()}</p>
                </div>
              </div>
              {!item.read && (
                <button
                  onClick={() => markAsRead(item.id)}
                  className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                  Mark read
                </button>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
