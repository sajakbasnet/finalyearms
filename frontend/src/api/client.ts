import axios from 'axios'

/**
 * One frontend build serves every tenant, so the API host cannot be baked in.
 *
 * In production each tenant is reached at its own hostname and the reverse
 * proxy forwards `/api` to that tenant's container — same origin, no CORS, and
 * the request necessarily lands on the right tenant. VITE_API_URL stays as an
 * escape hatch for split-host deployments and local development.
 */
function resolveBaseUrl(): string {
  const configured = import.meta.env.VITE_API_URL
  if (configured) {
    return configured
  }

  if (import.meta.env.PROD && typeof window !== 'undefined') {
    return `${window.location.origin}/api`
  }

  return 'http://127.0.0.1:8000/api'
}

const api = axios.create({
  baseURL: resolveBaseUrl(),
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('fyp_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('fyp_token')
      localStorage.removeItem('fyp_user')
      if (!window.location.pathname.startsWith('/login')) {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  },
)

export default api
