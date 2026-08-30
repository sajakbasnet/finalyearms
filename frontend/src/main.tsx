import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { applyBranding } from './branding/applyBranding.ts'
import { readCachedBranding } from './branding/brandingCache.ts'

// Apply the cached theme before React mounts so returning visitors never see
// the default palette flash. The live values are refreshed by BrandingProvider.
const cached = readCachedBranding()
if (cached) {
  applyBranding(cached)
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
