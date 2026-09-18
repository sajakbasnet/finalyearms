import api from './client'
import { isBranding, type Branding } from '../types/branding'

/**
 * Unauthenticated on purpose — the login screen has to render themed before
 * any token exists.
 */
export async function fetchBranding(): Promise<Branding> {
  const { data } = await api.get<{ data: unknown }>('/branding')

  if (!isBranding(data?.data)) {
    throw new Error('Invalid branding response')
  }

  return data.data
}

export async function updateBranding(formData: FormData): Promise<Branding> {
  const { data } = await api.post<{ data: unknown; message: string }>('/admin/branding', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })

  if (!isBranding(data?.data)) {
    throw new Error('Invalid branding response from server')
  }

  return data.data
}
