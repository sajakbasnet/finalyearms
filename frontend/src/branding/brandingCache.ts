import { isBranding, type Branding } from '../types/branding'

/**
 * Caches the resolved branding per hostname so repeat visits paint themed
 * immediately instead of flashing the default palette while `/api/branding`
 * is in flight.
 *
 * Keyed by hostname because one frontend deployment serves every tenant.
 */
function cacheKey(): string {
  const host = typeof window === 'undefined' ? 'unknown' : window.location.hostname
  return `fyp_branding:${host}`
}

export function readCachedBranding(): Branding | null {
  try {
    const stored = localStorage.getItem(cacheKey())
    if (!stored) {
      return null
    }

    const parsed: unknown = JSON.parse(stored)
    return isBranding(parsed) ? parsed : null
  } catch {
    // Private mode, blocked storage, or a stale shape — fall back to defaults.
    return null
  }
}

export function writeCachedBranding(branding: Branding): void {
  try {
    localStorage.setItem(cacheKey(), JSON.stringify(branding))
  } catch {
    // Caching is an optimisation; failing to store is not an error.
  }
}
