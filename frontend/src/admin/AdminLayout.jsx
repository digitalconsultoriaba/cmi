import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../auth/AuthProvider'
import { apiGet } from '../lib/api'

const MENU = [
  { to: '/painel', label: 'Evento', end: true, roles: ['admin'] },
  { to: '/painel/tipos-lotes', label: 'Tipos & Lotes', roles: ['admin'] },
  { to: '/painel/camisas', label: 'Camisas', roles: ['admin'] },
  { to: '/painel/landing', label: 'Landing', roles: ['admin'] },
  { to: '/painel/cortesias', label: 'Cortesias', roles: ['admin'] },
  { to: '/painel/patrocinios', label: 'Patrocínios', roles: ['admin'] },
  { to: '/painel/tesouraria', label: 'Tesouraria', roles: ['treasury', 'admin'] },
]

/** Resolve o evento gerenciado (single-event no MVP — primeiro da lista). */
export function useAdminEvent() {
  return useQuery({
    queryKey: ['admin', 'events'],
    queryFn: () => apiGet('/admin/events'),
    select: (events) => events[0] ?? null,
  })
}

export default function AdminLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const sair = async () => {
    await logout.mutateAsync()
    navigate('/entrar', { replace: true })
  }

  return (
    <div className="page">
      <aside className="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
        <div className="container-fluid">
          <h1 className="navbar-brand">
            <span className="fs-3">Plataforma de Eventos</span>
          </h1>
          <ul className="navbar-nav pt-lg-3">
            {MENU.filter((item) => item.roles.some((role) => user?.roles.includes(role)))
              .map((item) => (
                <li className="nav-item" key={item.to}>
                  <NavLink className="nav-link" to={item.to} end={item.end}>
                    {item.label}
                  </NavLink>
                </li>
              ))}
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
