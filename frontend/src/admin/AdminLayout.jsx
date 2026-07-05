import { useState } from 'react'
import { NavLink, Outlet, useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../auth/AuthProvider'
import { apiGet, apiPost } from '../lib/api'
import { Modal, ApiErrorAlert, useApiAction } from './components'

function homeFor(user) {
  // Admin e financeiro entram no módulo inteiro (spec 009)
  if (user?.roles.includes('admin') || user?.roles.includes('treasury')) return '/painel/dashboard'
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
  const [showPwd, setShowPwd] = useState(false)

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
                <NavLink className="nav-link" to="/painel/dashboard">Dashboard</NavLink>
              </li>
            )}
            {(isAdmin || isTreasury) && (
              <li className="nav-item">
                <NavLink className="nav-link" to="/painel/eventos" end>Eventos</NavLink>
              </li>
            )}
            {(isAdmin || isTreasury) && (
              <li className="nav-item">
                <NavLink className="nav-link" to="/painel/atendimentos">Atendimento</NavLink>
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
            {/* Sair — logo abaixo dos demais menus (ex.: Usuários) */}
            <li className="nav-item" style={{ borderTop: '1px solid rgba(255,255,255,.12)', marginTop: 4, paddingTop: 4 }}>
              <button type="button" className="nav-link border-0 bg-transparent text-start w-100"
                onClick={sair} style={{ cursor: 'pointer' }}>
                <span className="nav-link-title">Sair</span>
              </button>
            </li>
          </ul>
        </div>
      </aside>

      <div className="page-wrapper">
        <header className="navbar navbar-expand-md d-print-none">
          <div className="container-xl justify-content-end">
            <button type="button" className="btn btn-ghost-secondary btn-sm"
              onClick={() => setShowPwd(true)}>
              {user?.name} ▾
            </button>
          </div>
        </header>

        {showPwd && <AlterarSenhaModal onClose={() => setShowPwd(false)} />}
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

/** Modal de troca de senha do próprio usuário (topo do painel). */
function AlterarSenhaModal({ onClose }) {
  const { run, error, setError, busy } = useApiAction()
  const [pwd, setPwd] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [ok, setOk] = useState(false)
  const set = (f) => (e) => setPwd({ ...pwd, [f]: e.target.value })

  const salvar = () => run(() => apiPost('/auth/password', pwd), {
    onSuccess: () => { setOk(true); setPwd({ current_password: '', password: '', password_confirmation: '' }) },
  })

  return (
    <Modal title="Alterar minha senha" size="sm" onClose={onClose}
      footer={<>
        <button className="btn" onClick={onClose}>Fechar</button>
        <button className="btn btn-primary" disabled={busy || !pwd.password || pwd.password !== pwd.password_confirmation}
          onClick={salvar}>Alterar senha</button>
      </>}>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />
      {ok && <div className="alert alert-success">Senha alterada.</div>}
      <div className="mb-3">
        <label className="form-label">Senha atual</label>
        <input type="password" className="form-control" value={pwd.current_password}
          onChange={set('current_password')} autoComplete="current-password" />
      </div>
      <div className="mb-3">
        <label className="form-label">Nova senha</label>
        <input type="password" className="form-control" value={pwd.password}
          onChange={set('password')} placeholder="Mínimo 8 caracteres" autoComplete="new-password" />
      </div>
      <div className="mb-1">
        <label className="form-label">Confirme a nova senha</label>
        <input type="password" className="form-control" value={pwd.password_confirmation}
          onChange={set('password_confirmation')} autoComplete="new-password" />
      </div>
    </Modal>
  )
}
