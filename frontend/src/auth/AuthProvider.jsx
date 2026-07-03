import { createContext, useContext, useEffect } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, setOnUnauthenticated } from '../lib/api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const queryClient = useQueryClient()

  const { data: user, isLoading } = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: () => apiGet('/auth/me').catch(() => null),
    staleTime: 5 * 60 * 1000,
  })

  useEffect(() => {
    setOnUnauthenticated(() => queryClient.setQueryData(['auth', 'me'], null))
  }, [queryClient])

  const refresh = (me) => queryClient.setQueryData(['auth', 'me'], me)

  const login = useMutation({
    mutationFn: (credentials) => apiPost('/auth/login', credentials),
    onSuccess: refresh,
  })

  const register = useMutation({
    mutationFn: (payload) => apiPost('/auth/register', payload),
    onSuccess: refresh,
  })

  const logout = useMutation({
    mutationFn: () => apiPost('/auth/logout'),
    onSuccess: () => refresh(null),
  })

  const value = { user: user ?? null, isLoading, login, register, logout }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth precisa estar dentro de <AuthProvider>')
  }
  return context
}
