import { useState, useRef, type FormEvent, type ChangeEvent } from 'react'
import { useBranding } from '../../branding/BrandingContext'
import { updateBranding } from '../../api/branding'
import { defaultBranding, type BrandingColors } from '../../types/branding'

interface Preset {
  name: string
  colors: BrandingColors
}

const COLOR_PRESETS: Preset[] = [
  {
    name: 'White & Ocean Blue (Default)',
    colors: {
      primary: '#2563eb',
      accent: '#0284c7',
      ink: '#0f2b5c',
      paper: '#f8fafc',
    },
  },
  {
    name: 'Royal Navy & Gold',
    colors: {
      primary: '#1e3a8a',
      accent: '#d97706',
      ink: '#0f172a',
      paper: '#f8fafc',
    },
  },
  {
    name: 'Emerald Teal',
    colors: {
      primary: '#0d9488',
      accent: '#10b981',
      ink: '#134e4a',
      paper: '#f0fdf4',
    },
  },
  {
    name: 'Modern Indigo',
    colors: {
      primary: '#4f46e5',
      accent: '#818cf8',
      ink: '#1e1b4b',
      paper: '#f5f3ff',
    },
  },
  {
    name: 'Crimson Slate',
    colors: {
      primary: '#e11d48',
      accent: '#f43f5e',
      ink: '#1e293b',
      paper: '#fff1f2',
    },
  },
]

export function AdminBrandingPage() {
  const { branding, setBranding } = useBranding()

  // Form state
  const [institutionName, setInstitutionName] = useState(branding.institution_name || 'FYP Portal')
  const [shortName, setShortName] = useState(branding.short_name || '')
  const [tagline, setTagline] = useState(branding.tagline || '')
  const [colors, setColors] = useState<BrandingColors>({
    primary: branding.colors.primary || defaultBranding.colors.primary,
    accent: branding.colors.accent || defaultBranding.colors.accent,
    ink: branding.colors.ink || defaultBranding.colors.ink,
    paper: branding.colors.paper || defaultBranding.colors.paper,
  })

  // File uploads
  const [logoFile, setLogoFile] = useState<File | null>(null)
  const [logoPreview, setLogoPreview] = useState<string | null>(branding.logo_url)
  const [faviconFile, setFaviconFile] = useState<File | null>(null)
  const [faviconPreview, setFaviconPreview] = useState<string | null>(branding.favicon_url)

  const logoInputRef = useRef<HTMLInputElement>(null)
  const faviconInputRef = useRef<HTMLInputElement>(null)

  // Status
  const [isSaving, setIsSaving] = useState(false)
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null)

  const handleColorChange = (key: keyof BrandingColors, value: string) => {
    setColors((prev) => ({ ...prev, [key]: value }))
  }

  const applyPreset = (preset: Preset) => {
    setColors({ ...preset.colors })
    setFeedback({ type: 'success', message: `Applied palette preset: ${preset.name}` })
    setTimeout(() => setFeedback(null), 3000)
  }

  const handleResetToDefault = () => {
    setInstitutionName('FYP Portal')
    setShortName('')
    setTagline('Submit proposals, track progress, and stay in sync with your supervisor.')
    setColors({ ...defaultBranding.colors })
    setLogoFile(null)
    setLogoPreview(null)
    setFaviconFile(null)
    setFaviconPreview(null)
    setFeedback({ type: 'success', message: 'Reset fields to default FYP Portal values. Click Save to apply.' })
  }

  const handleLogoSelect = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (file) {
      if (file.size > 1024 * 1024) {
        setFeedback({ type: 'error', message: 'Logo file must be smaller than 1MB.' })
        return
      }
      setLogoFile(file)
      setLogoPreview(URL.createObjectURL(file))
    }
  }

  const handleFaviconSelect = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (file) {
      if (file.size > 256 * 1024) {
        setFeedback({ type: 'error', message: 'Favicon must be smaller than 256KB.' })
        return
      }
      setFaviconFile(file)
      setFaviconPreview(URL.createObjectURL(file))
    }
  }

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setIsSaving(true)
    setFeedback(null)

    try {
      const formData = new FormData()
      // If empty, backend and frontend fall back dynamically to 'FYP Portal'
      formData.append('institution_name', institutionName.trim() || 'FYP Portal')
      if (shortName.trim()) {
        formData.append('short_name', shortName.trim())
      }
      if (tagline.trim()) {
        formData.append('tagline', tagline.trim())
      }

      if (colors.primary) formData.append('color_primary', colors.primary)
      if (colors.accent) formData.append('color_accent', colors.accent)
      if (colors.ink) formData.append('color_ink', colors.ink)
      if (colors.paper) formData.append('color_paper', colors.paper)

      if (logoFile) {
        formData.append('logo', logoFile)
      }
      if (faviconFile) {
        formData.append('favicon', faviconFile)
      }

      const updated = await updateBranding(formData)
      setBranding(updated)
      setFeedback({ type: 'success', message: 'Portal branding and styling updated dynamically!' })
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to update branding settings.'
      setFeedback({ type: 'error', message })
    } finally {
      setIsSaving(false)
    }
  }

  // Dynamic preview values
  const effectiveTitle = institutionName.trim() || 'FYP Portal'
  const effectiveWordmark = shortName.trim() || effectiveTitle
  const effectiveTagline = tagline.trim() || 'Submit proposals, track progress, and stay in sync with your supervisor.'

  return (
    <div className="space-y-8">
      {/* Header Banner */}
      <div className="rounded-xl border border-blue-200/60 bg-gradient-to-r from-blue-50 via-white to-blue-50/50 p-6 shadow-sm">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-blue-800">
              Administrative Control
            </span>
            <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
              Portal Branding &amp; Customization
            </h1>
            <p className="mt-1 text-sm text-slate-600">
              Customize portal titles, sidebar wordmark, login page branding, and theme colors dynamically. All changes apply across the portal in real time.
            </p>
          </div>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={handleResetToDefault}
              className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition shadow-sm"
            >
              Reset to FYP Default
            </button>
          </div>
        </div>
      </div>

      {feedback && (
        <div
          className={`rounded-lg p-4 text-sm font-medium transition ${
            feedback.type === 'success'
              ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
              : 'bg-rose-50 text-rose-800 border border-rose-200'
          }`}
        >
          {feedback.message}
        </div>
      )}

      <form onSubmit={handleSubmit} className="grid grid-cols-1 gap-8 lg:grid-cols-3">
        {/* Main Configuration Form (2 Cols) */}
        <div className="space-y-6 lg:col-span-2">
          {/* Section: Dynamic Titles */}
          <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div className="border-b border-slate-100 pb-3">
              <h2 className="text-lg font-semibold text-slate-900">Portal Titles &amp; Labels</h2>
              <p className="text-xs text-slate-500 mt-0.5">
                Manage how your portal is named in the header, login page, and browser tab. Defaults to &quot;FYP Portal&quot; if left blank.
              </p>
            </div>

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700">
                  Institution / Main Portal Title <span className="text-slate-400 font-normal">(Default: FYP Portal)</span>
                </label>
                <input
                  type="text"
                  value={institutionName}
                  onChange={(e) => setInstitutionName(e.target.value)}
                  placeholder="e.g. FYP Portal or University of Engineering"
                  className="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                />
                <p className="mt-1 text-xs text-slate-500">
                  Appears as the primary title on the sign-in screen, browser tab, and exported reports. If empty, &quot;FYP Portal&quot; will be used.
                </p>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700">
                  Sidebar Wordmark / Short Name <span className="text-slate-400 font-normal">(Optional)</span>
                </label>
                <input
                  type="text"
                  value={shortName}
                  onChange={(e) => setShortName(e.target.value)}
                  placeholder="e.g. FYP Portal or IOE"
                  className="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                />
                <p className="mt-1 text-xs text-slate-500">
                  Displayed in the sidebar navigation header. If empty, falls back to the main portal title.
                </p>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700">
                  Portal Tagline / Subtitle
                </label>
                <input
                  type="text"
                  value={tagline}
                  onChange={(e) => setTagline(e.target.value)}
                  placeholder="Submit proposals, track progress, and stay in sync with your supervisor."
                  className="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                />
                <p className="mt-1 text-xs text-slate-500">
                  Displayed in the top navigation header and as the hero statement on the login screen.
                </p>
              </div>
            </div>
          </div>

          {/* Section: Theme Colors */}
          <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div className="border-b border-slate-100 pb-3">
              <h2 className="text-lg font-semibold text-slate-900">Dynamic Color Palette</h2>
              <p className="text-xs text-slate-500 mt-0.5">
                Customize the visual theme. Color changes are applied to the sidebar, top header, buttons, and backgrounds.
              </p>
            </div>

            {/* Presets */}
            <div>
              <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                1-Click Palette Presets:
              </span>
              <div className="mt-2.5 flex flex-wrap gap-2">
                {COLOR_PRESETS.map((preset) => (
                  <button
                    key={preset.name}
                    type="button"
                    onClick={() => applyPreset(preset)}
                    className="group inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-blue-500 hover:bg-blue-50/50 transition"
                  >
                    <span className="flex -space-x-1">
                      <span className="h-3.5 w-3.5 rounded-full border border-white shadow-xs" style={{ backgroundColor: preset.colors.primary || '#2563eb' }} />
                      <span className="h-3.5 w-3.5 rounded-full border border-white shadow-xs" style={{ backgroundColor: preset.colors.ink || '#0f2b5c' }} />
                      <span className="h-3.5 w-3.5 rounded-full border border-white shadow-xs" style={{ backgroundColor: preset.colors.accent || '#0284c7' }} />
                    </span>
                    <span>{preset.name}</span>
                  </button>
                ))}
              </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 pt-2">
              {/* Primary Color */}
              <div className="rounded-lg border border-slate-200 p-3.5 bg-slate-50/40">
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                  Primary Theme Color
                </label>
                <p className="text-xs text-slate-500 mb-2">Drives top header, buttons, active sidebar tab</p>
                <div className="flex items-center gap-3">
                  <input
                    type="color"
                    value={colors.primary || '#2563eb'}
                    onChange={(e) => handleColorChange('primary', e.target.value)}
                    className="h-10 w-12 cursor-pointer rounded border border-slate-300 bg-transparent p-0.5"
                  />
                  <input
                    type="text"
                    value={colors.primary || ''}
                    onChange={(e) => handleColorChange('primary', e.target.value)}
                    placeholder="#2563eb"
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-mono uppercase text-slate-800"
                  />
                </div>
              </div>

              {/* Accent Color */}
              <div className="rounded-lg border border-slate-200 p-3.5 bg-slate-50/40">
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                  Accent Color
                </label>
                <p className="text-xs text-slate-500 mb-2">Drives badges, status tags, subtle highlights</p>
                <div className="flex items-center gap-3">
                  <input
                    type="color"
                    value={colors.accent || '#0284c7'}
                    onChange={(e) => handleColorChange('accent', e.target.value)}
                    className="h-10 w-12 cursor-pointer rounded border border-slate-300 bg-transparent p-0.5"
                  />
                  <input
                    type="text"
                    value={colors.accent || ''}
                    onChange={(e) => handleColorChange('accent', e.target.value)}
                    placeholder="#0284c7"
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-mono uppercase text-slate-800"
                  />
                </div>
              </div>

              {/* Sidebar / Ink Color */}
              <div className="rounded-lg border border-slate-200 p-3.5 bg-slate-50/40">
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                  Sidebar &amp; Backdrop Color
                </label>
                <p className="text-xs text-slate-500 mb-2">Controls sidebar rail background and login screen</p>
                <div className="flex items-center gap-3">
                  <input
                    type="color"
                    value={colors.ink || '#0f2b5c'}
                    onChange={(e) => handleColorChange('ink', e.target.value)}
                    className="h-10 w-12 cursor-pointer rounded border border-slate-300 bg-transparent p-0.5"
                  />
                  <input
                    type="text"
                    value={colors.ink || ''}
                    onChange={(e) => handleColorChange('ink', e.target.value)}
                    placeholder="#0f2b5c"
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-mono uppercase text-slate-800"
                  />
                </div>
              </div>

              {/* Paper / Background Color */}
              <div className="rounded-lg border border-slate-200 p-3.5 bg-slate-50/40">
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                  Page Surface Neutral
                </label>
                <p className="text-xs text-slate-500 mb-2">Main page background (tables stay clean white)</p>
                <div className="flex items-center gap-3">
                  <input
                    type="color"
                    value={colors.paper || '#f8fafc'}
                    onChange={(e) => handleColorChange('paper', e.target.value)}
                    className="h-10 w-12 cursor-pointer rounded border border-slate-300 bg-transparent p-0.5"
                  />
                  <input
                    type="text"
                    value={colors.paper || ''}
                    onChange={(e) => handleColorChange('paper', e.target.value)}
                    placeholder="#f8fafc"
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-mono uppercase text-slate-800"
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Section: Brand Assets */}
          <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div className="border-b border-slate-100 pb-3">
              <h2 className="text-lg font-semibold text-slate-900">Brand Logo &amp; Favicon</h2>
              <p className="text-xs text-slate-500 mt-0.5">
                Upload your institution logo or browser favicon. PNG, JPG, or WebP up to 1MB.
              </p>
            </div>

            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
              {/* Logo */}
              <div className="space-y-3">
                <label className="block text-sm font-medium text-slate-700">Institution Logo</label>
                <div className="flex items-center gap-4">
                  <div className="flex h-16 w-24 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-2 overflow-hidden">
                    {logoPreview ? (
                      <img src={logoPreview} alt="Logo preview" className="max-h-full max-w-full object-contain" />
                    ) : (
                      <span className="text-xs font-semibold text-slate-400">No Logo</span>
                    )}
                  </div>
                  <div>
                    <input
                      ref={logoInputRef}
                      type="file"
                      accept="image/png,image/jpeg,image/webp"
                      onChange={handleLogoSelect}
                      className="hidden"
                    />
                    <button
                      type="button"
                      onClick={() => logoInputRef.current?.click()}
                      className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 shadow-xs transition"
                    >
                      {logoPreview ? 'Change Logo' : 'Upload Logo'}
                    </button>
                    <p className="mt-1 text-[11px] text-slate-400">PNG, JPG or WebP (max 1MB)</p>
                  </div>
                </div>
              </div>

              {/* Favicon */}
              <div className="space-y-3">
                <label className="block text-sm font-medium text-slate-700">Browser Favicon</label>
                <div className="flex items-center gap-4">
                  <div className="flex h-14 w-14 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-2 overflow-hidden">
                    {faviconPreview ? (
                      <img src={faviconPreview} alt="Favicon preview" className="h-8 w-8 object-contain" />
                    ) : (
                      <span className="text-xs font-semibold text-slate-400">ICO</span>
                    )}
                  </div>
                  <div>
                    <input
                      ref={faviconInputRef}
                      type="file"
                      accept="image/png,image/x-icon"
                      onChange={handleFaviconSelect}
                      className="hidden"
                    />
                    <button
                      type="button"
                      onClick={() => faviconInputRef.current?.click()}
                      className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 shadow-xs transition"
                    >
                      {faviconPreview ? 'Change Favicon' : 'Upload Favicon'}
                    </button>
                    <p className="mt-1 text-[11px] text-slate-400">PNG or ICO (max 256KB)</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Submit Button Bar */}
          <div className="flex items-center justify-end gap-3 pt-2">
            <button
              type="submit"
              disabled={isSaving}
              className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white shadow-sm hover:bg-blue-700 transition disabled:opacity-70 disabled:cursor-not-allowed"
            >
              {isSaving ? 'Applying Changes…' : 'Save & Apply Changes'}
            </button>
          </div>
        </div>

        {/* Real-time Interactive Preview (1 Col) */}
        <div className="space-y-6">
          <div className="sticky top-20 rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-5">
            <div className="border-b border-slate-100 pb-3">
              <span className="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider text-blue-600">
                <span className="h-2 w-2 rounded-full bg-blue-600 animate-pulse" />
                Live Preview
              </span>
              <h2 className="text-base font-semibold text-slate-900 mt-1">Real-time Visual Mockup</h2>
              <p className="text-xs text-slate-500">
                Shows how your current title and color choices will render to users.
              </p>
            </div>

            {/* Sidebar Mockup */}
            <div className="space-y-2">
              <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                1. Sidebar Navigation Preview
              </span>
              <div
                className="rounded-lg p-4 text-white shadow-md transition-colors"
                style={{ backgroundColor: colors.ink || '#0f2b5c' }}
              >
                <div className="border-b border-white/10 pb-2">
                  <p className="text-base font-bold tracking-tight">
                    {effectiveWordmark}
                  </p>
                  <p className="text-[10px] uppercase tracking-wider font-semibold text-white/75 mt-0.5">
                    Admin Console
                  </p>
                </div>
                <div className="mt-3 space-y-1.5 text-xs">
                  <div
                    className="rounded-md px-2.5 py-1.5 font-semibold text-white shadow-xs"
                    style={{ backgroundColor: colors.primary || '#2563eb' }}
                  >
                    Users &amp; Roles
                  </div>
                  <div className="rounded-md px-2.5 py-1.5 text-white/80 hover:bg-white/10">
                    Portal Branding
                  </div>
                  <div className="rounded-md px-2.5 py-1.5 text-white/80 hover:bg-white/10">
                    Departments
                  </div>
                </div>
              </div>
            </div>

            {/* Top Header Mockup */}
            <div className="space-y-2">
              <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                2. Header Bar Preview
              </span>
              <div
                className="flex items-center justify-between rounded-lg px-4 py-2.5 text-white shadow-sm transition-colors"
                style={{ backgroundColor: colors.primary || '#2563eb' }}
              >
                <p className="text-xs font-medium truncate max-w-[180px]">
                  {effectiveTagline}
                </p>
                <span className="rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-medium text-white">
                  Admin Active
                </span>
              </div>
            </div>

            {/* Login Screen Mockup */}
            <div className="space-y-2">
              <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                3. Login Screen Preview
              </span>
              <div
                className="relative overflow-hidden rounded-lg p-5 text-white shadow-md transition-colors"
                style={{
                  backgroundColor: colors.ink || '#0f2b5c',
                  backgroundImage: `linear-gradient(135deg, ${colors.ink || '#0f2b5c'} 0%, ${colors.primary || '#2563eb'} 100%)`,
                }}
              >
                <div className="relative z-10 space-y-3">
                  <p className="text-sm font-bold tracking-tight text-white">
                    {effectiveTitle}
                  </p>
                  <p className="text-[11px] text-white/85 line-clamp-2 leading-tight">
                    {effectiveTagline}
                  </p>
                  <div className="rounded-md bg-white p-3 text-slate-900 shadow-sm space-y-2">
                    <p className="text-[11px] font-semibold text-slate-700">Sign in to Portal</p>
                    <div className="h-6 rounded border border-slate-200 bg-slate-50" />
                    <div className="h-6 rounded border border-slate-200 bg-slate-50" />
                    <div
                      className="rounded py-1 text-center text-[10px] font-semibold text-white shadow-xs"
                      style={{ backgroundColor: colors.primary || '#2563eb' }}
                    >
                      Sign in
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  )
}
