import { useBranding } from '../branding/BrandingContext'

/**
 * Institution logo where one is configured, wordmark otherwise.
 *
 * `short_name` wins in the sidebar because full institution names ("Institute
 * of Engineering, Pulchowk Campus") overflow a 18rem rail.
 */
export function BrandMark({
  variant = 'sidebar',
  className = '',
}: {
  variant?: 'sidebar' | 'hero'
  className?: string
}) {
  const { branding } = useBranding()
  const isHero = variant === 'hero'

  if (branding.logo_url) {
    return (
      <img
        src={branding.logo_url}
        alt={branding.institution_name}
        className={`${isHero ? 'max-h-20' : 'max-h-10'} w-auto object-contain ${className}`}
      />
    )
  }

  const text = isHero
    ? branding.institution_name
    : (branding.short_name ?? branding.institution_name)

  return (
    <p
      className={[
        'font-[family-name:var(--font-display)] tracking-tight',
        isHero ? 'text-5xl leading-none sm:text-6xl md:text-7xl' : 'text-2xl',
        className,
      ].join(' ')}
    >
      {text}
    </p>
  )
}
