import type { Branding } from '../types/branding'

/**
 * Applies a tenant's branding by overriding theme custom properties on the
 * root element. Inline styles set here beat the `:root` rules Tailwind emits
 * from `@theme`, so the whole app re-themes without a rebuild.
 */

const WHITE = '#ffffff'
const FALLBACK_ON_LIGHT = '#0f1c24' // --color-ink default

function parseHex(hex: string): [number, number, number] | null {
  const value = hex.trim().replace(/^#/, '')
  const full =
    value.length === 3
      ? value
          .split('')
          .map((c) => c + c)
          .join('')
      : value

  if (!/^[0-9a-fA-F]{6}$/.test(full)) {
    return null
  }

  return [
    parseInt(full.slice(0, 2), 16),
    parseInt(full.slice(2, 4), 16),
    parseInt(full.slice(4, 6), 16),
  ]
}

/** WCAG relative luminance. */
function luminance(rgb: [number, number, number]): number {
  const [r, g, b] = rgb.map((channel) => {
    const c = channel / 255
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  }) as [number, number, number]

  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

function contrastRatio(a: number, b: number): number {
  const [lighter, darker] = a > b ? [a, b] : [b, a]
  return (lighter + 0.05) / (darker + 0.05)
}

/**
 * Picks whichever of white / near-black reads better on `background`.
 *
 * Without this a university whose brand colour is a pale gold gets white text
 * on a near-white surface. Falls back to white for unparseable input so a bad
 * value can't blank the UI.
 */
export function readableForeground(background: string): string {
  const rgb = parseHex(background)
  if (!rgb) {
    return WHITE
  }

  const bg = luminance(rgb)
  const onWhite = contrastRatio(bg, luminance([255, 255, 255]))
  const onInk = contrastRatio(bg, luminance(parseHex(FALLBACK_ON_LIGHT)!))

  return onWhite >= onInk ? WHITE : FALLBACK_ON_LIGHT
}

/**
 * Derivations run through CSS `color-mix()` rather than JS colour maths: the
 * browser does the blending, and the ramp stays consistent with the tints
 * defined in index.css.
 */
function mix(color: string, amount: number, towards: 'black' | 'white'): string {
  return `color-mix(in srgb, ${color} ${amount}%, ${towards})`
}

function setVars(root: HTMLElement, vars: Record<string, string | null>): void {
  for (const [name, value] of Object.entries(vars)) {
    if (value) {
      root.style.setProperty(name, value)
    }
  }
}

export function applyBranding(branding: Branding, target?: HTMLElement): void {
  const root = target ?? document.documentElement
  const { primary, accent, ink, paper } = branding.colors

  if (primary) {
    setVars(root, {
      '--color-sea': primary,
      '--color-sea-deep': mix(primary, 70, 'black'),
      '--color-sea-soft': mix(primary, 16, 'white'),
      '--color-on-brand': readableForeground(primary),
      '--color-header': primary,
      '--color-on-header': readableForeground(primary),
    })
  }

  if (accent) {
    setVars(root, {
      '--color-amber': accent,
      '--color-amber-soft': mix(accent, 22, 'white'),
    })
  }

  if (ink) {
    setVars(root, {
      '--color-ink': ink,
      '--color-ink-deep': mix(ink, 70, 'black'),
      '--color-ink-muted': mix(ink, 68, 'white'),
      '--color-on-ink': readableForeground(ink),
      '--color-sidebar': ink,
    })
  }

  if (paper) {
    setVars(root, {
      '--color-paper': paper,
      '--color-paper-deep': mix(paper, 88, 'black'),
      '--color-surface': WHITE,
    })
  }

  setVars(root, {
    '--font-display': branding.font_display,
    '--font-sans': branding.font_sans,
  })

  applyDocumentChrome(branding)
}

/** Title, favicon, and any tenant font stylesheet. */
function applyDocumentChrome(branding: Branding): void {
  if (typeof document === 'undefined') {
    return
  }

  document.title = branding.institution_name?.trim() || 'FYP Portal'

  if (branding.favicon_url) {
    let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
    if (!link) {
      link = document.createElement('link')
      link.rel = 'icon'
      document.head.appendChild(link)
    }
    link.href = branding.favicon_url
    link.removeAttribute('type')
  }

  if (branding.font_stylesheet_url) {
    const id = 'tenant-font-stylesheet'
    let sheet = document.getElementById(id) as HTMLLinkElement | null
    if (!sheet) {
      sheet = document.createElement('link')
      sheet.id = id
      sheet.rel = 'stylesheet'
      document.head.appendChild(sheet)
    }
    sheet.href = branding.font_stylesheet_url
  }
}
