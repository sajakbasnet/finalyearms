import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { fetchBranding } from '../api/branding'
import { applyBranding } from './applyBranding'
import { readCachedBranding, writeCachedBranding } from './brandingCache'
import { defaultBranding, type Branding } from '../types/branding'

interface BrandingContextValue {
  branding: Branding
  /** False until the live response has been applied; cached values render immediately. */
  isResolved: boolean
}

const BrandingContext = createContext<BrandingContextValue | undefined>(undefined)

export function BrandingProvider({ children }: { children: ReactNode }) {
  const [branding, setBranding] = useState<Branding>(
    () => readCachedBranding() ?? defaultBranding,
  )
  const [isResolved, setIsResolved] = useState(false)

  useEffect(() => {
    let cancelled = false

    void fetchBranding()
      .then((live) => {
        if (cancelled) {
          return
        }
        setBranding(live)
        applyBranding(live)
        writeCachedBranding(live)
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
    <BrandingContext.Provider value={{ branding, isResolved }}>
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
