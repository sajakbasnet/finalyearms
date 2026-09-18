import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { fetchBranding } from '../api/branding'
import { applyBranding } from './applyBranding'
import { readCachedBranding, writeCachedBranding } from './brandingCache'
import { defaultBranding, type Branding } from '../types/branding'

interface BrandingContextValue {
  branding: Branding
  /** False until the live response has been applied; cached values render immediately. */
  isResolved: boolean
  setBranding: (branding: Branding) => void
  reloadBranding: () => Promise<void>
}

const BrandingContext = createContext<BrandingContextValue | undefined>(undefined)

export function BrandingProvider({ children }: { children: ReactNode }) {
  const [branding, setBrandingState] = useState<Branding>(
    () => readCachedBranding() ?? defaultBranding,
  )
  const [isResolved, setIsResolved] = useState(false)

  const updateBranding = (newBranding: Branding) => {
    setBrandingState(newBranding)
    applyBranding(newBranding)
    writeCachedBranding(newBranding)
  }

  const reloadBranding = async () => {
    try {
      const live = await fetchBranding()
      if (live) {
        updateBranding(live)
      }
    } catch {
      // Retain existing branding if offline
    }
  }

  useEffect(() => {
    let cancelled = false

    void fetchBranding()
      .then((live) => {
        if (cancelled || !live) {
          return
        }
        updateBranding(live)
      })
      .catch(() => {
        // Keep whatever is already applied (cache or shipped defaults) — a
        // branding outage must not block sign-in.
      })
      .finally(() => {
        if (!cancelled) {
          setIsResolved(true)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  return (
    <BrandingContext.Provider value={{ branding, isResolved, setBranding: updateBranding, reloadBranding }}>
      {children}
    </BrandingContext.Provider>
  )
}

export function useBranding(): BrandingContextValue {
  const context = useContext(BrandingContext)
  if (!context) {
    throw new Error('useBranding must be used within BrandingProvider')
  }
  return context
}
