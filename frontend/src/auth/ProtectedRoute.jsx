import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from './AuthProvider'
import Loading from '../components/Loading'

// Sem sessão → /entrar guardando o destino; após o login, volta (FR-012).
export default function ProtectedRoute({ children }) {
  const { user, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return <Loading />
  }

  if (!user) {
    return <Navigate to="/entrar" state={{ from: location }} replace />
  }

  return children
}
