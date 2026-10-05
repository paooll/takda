import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { api, clearToken, getToken, setToken, type Role, type User } from './api'

interface AuthValue {
  user: User | null
  loading: boolean
  signIn: (email: string, password: string) => Promise<void>
  signUp: (data: {
    name: string
    email: string
    password: string
    role?: Role
    phone?: string
  }) => Promise<void>
  signOut: () => Promise<void>
}

const AuthContext = createContext<AuthValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!getToken()) {
      setLoading(false)
      return
    }

    api
      .me()
      .then(({ data }) => setUser(data.user))
      .catch(() => clearToken())
      .finally(() => setLoading(false))
  }, [])

  const signIn = useCallback(async (email: string, password: string) => {
    const { data } = await api.login(email, password)
    setToken(data.token)
    setUser(data.user)
  }, [])

  const signUp = useCallback(
    async (input: { name: string; email: string; password: string; role?: Role; phone?: string }) => {
      const { data } = await api.register({ ...input, password_confirmation: input.password })
      setToken(data.token)
      setUser(data.user)
    },
    [],
  )

  const signOut = useCallback(async () => {
    try {
      await api.logout()
    } catch {
      // A dead token should still clear the client.
    }
    clearToken()
    setUser(null)
  }, [])

  const value = useMemo(
    () => ({ user, loading, signIn, signUp, signOut }),
    [user, loading, signIn, signUp, signOut],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthValue {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider')
  }

  return context
}
