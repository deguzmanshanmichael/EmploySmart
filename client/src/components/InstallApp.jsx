import { FiDownload } from 'react-icons/fi'
import { useInstallPrompt } from '../hooks/useInstallPrompt'
import toast from 'react-hot-toast'

export function InstallApp() {
  const { canInstall, handleInstall, isIOS } = useInstallPrompt()

  if (!canInstall && !isIOS) return null

  const onInstallClick = async () => {
    try {
      await handleInstall()
      toast.success('App installed successfully!')
    } catch (err) {
      toast.error('Failed to install app')
    }
  }

  return (
    <>
      {canInstall && (
        <button
          onClick={onInstallClick}
          className="flex items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
          title="Install EmploySmart app on your device"
        >
          <FiDownload size={16} />
          <span className="hidden sm:inline">Install App</span>
        </button>
      )}
      {isIOS && (
        <button
          disabled
          className="flex items-center gap-2 px-3 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium cursor-help"
          title="On iOS, tap Share and select 'Add to Home Screen'"
        >
          <FiDownload size={16} />
          <span className="hidden sm:inline">Add to Home</span>
        </button>
      )}
    </>
  )
}
