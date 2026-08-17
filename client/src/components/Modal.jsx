import { FiX } from 'react-icons/fi'

export function Modal({ title, onClose, footer, children }) {
  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-lg max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-lg font-semibold">{title}</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <FiX size={20} />
          </button>
        </div>
        <div className="p-4">
          {children}
        </div>
        {footer && (
          <div className="flex justify-end gap-2 p-4 border-t">
            {footer}
          </div>
        )}
      </div>
    </div>
  )
}