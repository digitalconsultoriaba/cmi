import { NavLink, Outlet, useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../auth/AuthProvider'
import { apiGet } from '../lib/api'

function homeFor(user) {
  // Admin e financeiro entram no módulo inteiro (spec 009)
  if (user?.roles.includes('admin') || user?.roles.includes('treasury')) return '/painel/modulo'
  return '/painel/checkin'
}

/**
 * Evento gerenciado. Dentro de `/painel/eventos/:eventId` resolve o evento da
 * URL; fora dele (contexto tesouraria/portaria), cai no primeiro evento —
 * assim as telas reembaladas operam no evento certo sem alteração.
 */
export function useAdminEvent() {
  const { eventId } = useParams()

  return useQuery({
    queryKey: ['admin', 'event', eventId ?? 'first'],
    queryFn: async () => {
      if (eventId) return apiGet(`/admin/events/${eventId}`)
      const events = await apiGet('/admin/events')
      return events[0] ?? null
    },
  })
}

export default function AdminLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const sair = async () => {
    await logout.mutateAsync()
    navigate('/entrar', { replace: true })
  }

  const home = homeFor(user)
  const isAdmin = user?.roles.includes('admin')
  const isTreasury = user?.roles.includes('treasury')
  const isGate = user?.roles.includes('gate')

  return (
    <div className="page" data-bs-theme="light">
      <aside className="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
        <div className="container-fluid">
          <h1 className="navbar-brand navbar-brand-autodark d-flex align-items-center">
            <img src="/logo.png" alt="CMI · GLMEES" height="44"
              style={{ background: '#fff', borderRadius: 8, padding: 4 }} />
          </h1>

          <ul className="navbar-nav pt-lg-3">
            {(isAdmin || isTreasury) && (
              <li className="nav-item">
                <NavLink className="nav-link" to="/painel/modulo">Eventos e Ingressos</NavLink>
              </li>
            )}
            {(isAdmin || isTreasury) && (
              <li className="nav-item">
                <NavLink className="nav-link" to="/painel/financas">Financeiro</NavLink>
              </li>
            )}
            {isTreasury && (
              <li className="nav-item">
                <NavLink className="nav-link" to="/painel/financeiro" end>Recebimentos (ingressos)</NavLink>
              </li>
            )}
            {isAdmin && (
              <li className="nav-item">
                <NavLink className="nav-link" to="/painel/usuarios">Usuários</NavLink>
              </li>
            )}
            {isGate && (
              <li className="nav-item">
                <NavLink className="nav-link" to="/painel/checkin">Check-in</NavLink>
              </li>
            )}
          </ul>
        </div>
      </aside>

      <div className="page-wrapper">
        <header className="navbar navbar-expand-md d-print-none">
          <div className="container-xl justify-content-end">
            <span className="me-3 text-secondary">{user?.name}</span>
            <button type="button" className="btn btn-outline-secondary btn-sm" onClick={sair}>
              Sair
            </button>
          </div>
        </header>
        <div className="page-body">
          <div className="container-xl">
            <Outlet />
          </div>
        </div>
      </div>
    </div>
  )
}

export { homeFor }
