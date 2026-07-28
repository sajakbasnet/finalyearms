import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import * as authApi from '../api/auth'
import type { RoleSlug, User } from '../types/auth'

interface AuthContextValue {
  user: User | null
  token: string | null
  isLoading: boolean
  login: (email: string, password: string) => Promise<User>
  logout: () => Promise<void>
  hasRole: (role: RoleSlug) => boolean
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(() => {
    const stored = localStorage.getItem('fyp_user')
    return stored ? (JSON.parse(stored) as User) : null
  })
  const [token, setToken] = useState<string | null>(() => localStorage.getItem('fyp_token'))
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    async function bootstrap() {
      if (!token) {
        setIsLoading(false)
        return
      }

      try {
        const me = await authApi.fetchMe()
        setUser(me)
        localStorage.setItem('fyp_user', JSON.stringify(me))
      } catch {
        localStorage.removeItem('fyp_token')
        localStorage.removeItem('fyp_user')
        setToken(null)
        setUser(null)
      } finally {
        setIsLoading(false)
      }
    }

    void bootstrap()
  }, [token])

  const login = useCallback(async (email: string, password: string) => {
    const response = await authApi.login(email, password)
    localStorage.setItem('fyp_token', response.token)
    localStorage.setItem('fyp_user', JSON.stringify(response.user))
    setToken(response.token)
    setUser(response.user)
    return response.user
  }, [])

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // Ignore network errors on logout.
    } finally {
      localStorage.removeItem('fyp_token')
      localStorage.removeItem('fyp_user')
      setToken(null)
      setUser(null)
    }
  }, [])

  const hasRole = useCallback(
    (role: RoleSlug) => user?.role?.slug === role,
    [user],
  )

  const value = useMemo(
    () => ({ user, token, isLoading, login, logout, hasRole }),
    [user, token, isLoading, login, logout, hasRole],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider')
  }
  return context
}
