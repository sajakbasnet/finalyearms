import api from './client'
import type { Branding } from '../types/branding'

/**
 * Unauthenticated on purpose — the login screen has to render themed before
 * any token exists.
 */
export async function fetchBranding(): Promise<Branding> {
  const { data } = await api.get<{ data: Branding }>('/branding')
  return data.data
}
