/**
 * Per-tenant branding, served unauthenticated by the tenant's own backend at
 * `GET /api/branding` so the login screen can render themed before any token
 * exists.
 *
 * Only four base colours are tenant-supplied. Every other token in the palette
 * is derived from them in `applyBranding()`, which keeps the surface an
 * institution has to fill in small enough that the result stays coherent.
 */
export interface BrandingColors {
  /** Primary brand colour. Drives --color-sea{,-deep,-soft}. */
  primary: string | null
  /** Secondary/accent colour. Drives --color-amber{,-soft}. */
  accent: string | null
  /** Darkest neutral — sidebar, login backdrop. Drives --color-ink{,-deep,-muted}. */
  ink: string | null
  /** Page background neutral. Drives --color-paper{,-deep}. */
  paper: string | null
}

export interface Branding {
  institution_name: string
  short_name: string | null
  tagline: string | null
  logo_url: string | null
  favicon_url: string | null
  colors: BrandingColors
  font_display: string | null
  font_sans: string | null
  /** Optional Google Fonts stylesheet to inject for the two families above. */
  font_stylesheet_url: string | null
}

/** Shipped defaults — also the fallback when the branding request fails. */
export const defaultBranding: Branding = {
  institution_name: 'FYP Portal',
  short_name: null,
  tagline: 'Submit proposals, track progress, and stay in sync with your supervisor.',
  logo_url: null,
  favicon_url: null,
  colors: {
    primary: '#2563eb',
    accent: '#0284c7',
    ink: '#0f2b5c',
    paper: '#f8fafc',
  },
  font_display: null,
  font_sans: null,
  font_stylesheet_url: null,
}

/** Guards against malformed API responses and stale cache entries. */
export function isBranding(value: unknown): value is Branding {
  return (
    typeof value === 'object'
    && value !== null
    && typeof (value as Branding).institution_name === 'string'
  )
}
