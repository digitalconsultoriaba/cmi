import { useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../auth/AuthProvider'
import { apiPost, apiUpload } from '../lib/api'
import { parseApiError } from '../lib/forms'

function initials(name) {
  return (name ?? '?').split(' ').slice(0, 2).map((s) => s[0]).join('').toUpperCase()
}

const TABS = [
  { to: '/minha-conta', label: 'Meus Dados', end: true },
  { to: '/minha-conta/pedidos', label: 'Meus Pedidos' },
  { to: '/minha-conta/ingressos', label: 'Meus Ingressos' },
  { to: '/minha-conta/suporte', label: 'Suporte' },
]

export default function MinhaConta() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [resent, setResent] = useState(false)
  const [error, setError] = useState(null)

  if (!user) return null

  const refreshMe = () => queryClient.invalidateQueries({ queryKey: ['auth', 'me'] })

  const enviarFoto = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    setError(null)
    const data = new FormData(); data.append('avatar', file)
    try { await apiUpload('/auth/avatar', data); refreshMe() } catch (err) { setError(parseApiError(err)) }
  }
  const reenviar = async () => {
    try { await apiPost('/auth/email/resend'); setResent(true) } catch (err) { setError(parseApiError(err)) }
  }
  const sair = async () => { await logout.mutateAsync(); navigate('/entrar', { replace: true }) }

  return (
    <div className="page" data-bs-theme="light" style={{ background: '#f5f7fb', minHeight: '100vh' }}>
      {/* Cabeçalho fixo — permanece ao trocar de aba */}
      <header className="d-print-none" style={{
        position: 'sticky', top: 0, zIndex: 1020, background: '#fff',
        borderBottom: '1px solid #e6e7e9', boxShadow: '0 1px 3px rgba(0,0,0,.04)',
      }}>
        <div className="container-xl d-flex align-items-center py-2">
          <Link to="/"><img src="/logo.png" alt="CMI · GLMEES"
            style={{ height: 100, width: 'auto', objectFit: 'contain' }} /></Link>
          <div className="ms-auto d-flex align-items-center gap-2">
            <span className="text-secondary d-none d-sm-inline">{user.name}</span>
            <button className="btn btn-sm" onClick={sair} disabled={logout.isPending}>Sair</button>
          </div>
        </div>

        {/* Faixa do perfil */}
        <div className="container-xl d-flex align-items-center gap-3 pb-2 flex-wrap">
          {user.avatarUrl ? (
            <img src={user.avatarUrl} alt={user.name}
              style={{ width: 56, height: 56, borderRadius: '50%', objectFit: 'cover', border: '2px solid #e6e7e9' }} />
          ) : (
            <span className="avatar avatar-lg"
              style={{ background: 'var(--brand-blue, #1b3a6b)', color: '#fff', fontSize: '1.25rem' }}>
              {initials(user.name)}
            </span>
          )}
          <div>
            <div className="h3 mb-0">{user.name}</div>
            <div className="text-secondary small">
              {user.email}{' '}
              {user.emailVerified
                ? <span className="badge bg-success text-white ms-1">verificado</span>
                : <span className="badge bg-warning text-dark ms-1">não verificado</span>}
            </div>
          </div>
          <label className="btn btn-sm ms-auto mb-0">
            Trocar foto
            <input type="file" hidden accept="image/jpeg,image/png,image/webp" onChange={enviarFoto} />
          </label>
        </div>

        {/* Abas */}
        <div className="container-xl">
          <ul className="nav nav-tabs border-0" style={{ marginBottom: -1 }}>
            {TABS.map((tab) => (
              <li className="nav-item" key={tab.to}>
                <NavLink to={tab.to} end={tab.end}
                  className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}>
                  {tab.label}
                </NavLink>
              </li>
            ))}
          </ul>
        </div>
      </header>

      <div className="page-body mt-0">
        <div className="container-xl py-3">
          {error && <div className="alert alert-danger">{error.message}</div>}
          {!user.emailVerified && (
            <div className="alert alert-warning">
              Confirme seu e-mail para o acesso completo.{' '}
              {resent ? 'E-mail reenviado — confira a caixa de entrada.'
                : <button className="btn btn-sm btn-warning ms-2" onClick={reenviar}>Reenviar confirmação</button>}
            </div>
          )}
          <Outlet />
        </div>
      </div>
    </div>
  )
}
