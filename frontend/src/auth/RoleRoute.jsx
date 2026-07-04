import { Link } from 'react-router-dom'
import ProtectedRoute from './ProtectedRoute'
import { useAuth } from './AuthProvider'

function RoleGate({ roles, children }) {
  const { user } = useAuth()

  if (!roles.some((role) => user.roles.includes(role))) {
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
// Aceita `role="admin"` ou `roles={['admin','treasury']}` (qualquer um).
export default function RoleRoute({ role, roles, children }) {
  const required = roles ?? [role]

  return (
    <ProtectedRoute>
      <RoleGate roles={required}>{children}</RoleGate>
    </ProtectedRoute>
  )
}
