import { Link } from 'react-router-dom'
import ProtectedRoute from './ProtectedRoute'
import { useAuth } from './AuthProvider'

function RoleGate({ role, children }) {
  const { user } = useAuth()

  if (!user.roles.includes(role)) {
    return (
      <main style={{ maxWidth: 480, margin: '6rem auto', textAlign: 'center', fontFamily: 'sans-serif' }}>
        <h1>403</h1>
        <p>Você não tem permissão para acessar esta área.</p>
        <Link to="/">Voltar ao início</Link>
      </main>
    )
  }

  return children
}

// Sem sessão → login (ProtectedRoute); logado sem papel → 403 (sem redirect).
export default function RoleRoute({ role, children }) {
  return (
    <ProtectedRoute>
      <RoleGate role={role}>{children}</RoleGate>
    </ProtectedRoute>
  )
}
