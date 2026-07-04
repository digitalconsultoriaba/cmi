import { Link, Navigate } from 'react-router-dom'
import ProtectedRoute from './ProtectedRoute'
import { useAuth } from './AuthProvider'

function RoleGate({ roles, children }) {
  const { user } = useAuth()

  if (!roles.some((role) => user.roles.includes(role))) {
    // Inscrito (sem papel de equipe) não deve ver o painel — mandamos para a
    // conta dele em vez de um beco sem saída.
    const isStaff = user.roles.some((r) => ['admin', 'treasury', 'gate'].includes(r))
    if (!isStaff) {
      return <Navigate to="/minha-conta" replace />
    }

    return (
      <div className="page page-center" data-bs-theme="light" style={{ minHeight: '100vh' }}>
        <div className="container container-tight py-4 text-center">
          <div className="empty">
            <div className="empty-header">403</div>
            <p className="empty-title">Você não tem permissão para acessar esta área.</p>
            <div className="empty-action">
              <Link className="btn btn-primary" to="/painel">Ir para o painel</Link>
            </div>
          </div>
        </div>
      </div>
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
