import { NavLink } from 'react-router-dom'

export default function Sidebar({ links, open, onClose, roleLabel, roleColor }) {
  return (
    <>
      {/* Mobile overlay */}
      {open && (
        <div
          className="fixed inset-0 bg-black/40 backdrop-blur-sm z-30 lg:hidden"
          onClick={onClose}
        />
      )}

      {/* Sidebar panel */}
      <aside
        className={`
          fixed top-16 left-0 bottom-0 z-40 w-64 bg-white border-r border-gray-100 overflow-y-auto
          transition-transform duration-300 ease-in-out
          ${open ? 'translate-x-0' : '-translate-x-full'}
          lg:translate-x-0 lg:static lg:top-0 lg:z-auto
        `}
      >
        <div className="p-4">
          {/* Role badge */}
          <div className={`mb-5 px-3 py-2 rounded-lg ${roleColor} text-xs font-bold uppercase tracking-wide`}>
            {roleLabel}
          </div>

          <nav className="space-y-1">
            {links.map((link) =>
              link.type === 'divider' ? (
                <div key={link.label} className="pt-4 pb-1">
                  <p className="text-xs font-bold text-gray-400 uppercase tracking-wider px-3">
                    {link.label}
                  </p>
                </div>
              ) : (
                <NavLink
                  key={link.to}
                  to={link.to}
                  end={link.end}
                  onClick={onClose}
                  className={({ isActive }) =>
                    `sidebar-link ${isActive ? 'sidebar-link-active' : 'sidebar-link-inactive'}`
                  }
                >
                  <span className="text-lg flex-shrink-0">{link.icon}</span>
                  <span>{link.label}</span>
                </NavLink>
              )
            )}
          </nav>
        </div>
      </aside>
    </>
  )
}