import { useEffect, useState } from 'react'
import { settingsService } from '../services/index'

/**
 * Municipality details configured by the administrator (Admin → Municipality Settings),
 * so the same build can serve any LGU. Loaded once and shared by every component.
 */
const FALLBACK = {
  municipality_name: '',
  municipality_province: '',
  municipality_region: '',
  contact_office: 'PESO',
  contact_address: '',
  contact_email: '',
  contact_phone: '',
  office_hours: '',
}

let cached = null
let pending = null

function load() {
  if (cached) return Promise.resolve(cached)
  if (!pending) {
    pending = settingsService.getLanding()
      .then((res) => { cached = { ...FALLBACK, ...(res.data?.data || {}) }; return cached })
      .catch(() => { pending = null; return FALLBACK })
  }
  return pending
}

/** "San Jose City, Nueva Ecija" style label from the configured name and province. */
export function placeLabel(config) {
  const name = (config?.municipality_name || '').trim()
  const province = (config?.municipality_province || '').trim()
  const generic = !name || /^employsmart municipality$/i.test(name)
  return [generic ? '' : name, province].filter(Boolean).join(', ')
}

export default function useMunicipality() {
  const [config, setConfig] = useState(cached || FALLBACK)
  useEffect(() => {
    let active = true
    load().then((c) => { if (active) setConfig(c) })
    return () => { active = false }
  }, [])
  return { config, place: placeLabel(config), loaded: config !== FALLBACK }
}
