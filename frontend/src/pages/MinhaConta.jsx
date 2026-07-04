import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../auth/AuthProvider'
import { apiPost, apiPut, apiUpload } from '../lib/api'
import { parseApiError, fieldError } from '../lib/forms'

function initials(name) {
  return (name ?? '?').split(' ').slice(0, 2).map((s) => s[0]).join('').toUpperCase()
}

export default function MinhaConta() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [resent, setResent] = useState(false)
  const [error, setError] = useState(null)
  const [ok, setOk] = useState(null)

  const [profile, setProfile] = useState(null)
  const [pwd, setPwd] = useState({ current_password: '', password: '', password_confirmation: '' })

  if (!user) return null

  const form = profile ?? { name: user.name, phone: user.phone ?? '', document: user.document ?? '' }
  const setForm = (f) => (e) => setProfile({ ...form, [f]: e.target.value })
  const refreshMe = () => queryClient.invalidateQueries({ queryKey: ['auth', 'me'] })

  const wrap = async (fn, msg) => {
    setError(null); setOk(null)
    try { await fn(); setOk(msg); refreshMe() } catch (err) { setError(parseApiError(err)) }
  }

  const salvarDados = () => wrap(() => apiPut('/auth/profile', form), 'Dados atualizados.')
  const salvarSenha = () => wrap(async () => { await apiPost('/auth/password', pwd); setPwd({ current_password: '', password: '', password_confirmation: '' }) }, 'Senha alterada.')
  const enviarFoto = (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    const data = new FormData(); data.append('avatar', file)
    wrap(() => apiUpload('/auth/avatar', data), 'Foto atualizada.')
  }
  const reenviar = () => wrap(async () => { await apiPost('/auth/email/resend'); setResent(true) }, 'E-mail reenviado.')
  const sair = async () => { await logout.mutateAsync(); navigate('/entrar', { replace: true }) }

  return (
    <div className="page" data-bs-theme="light">
      <header className="navbar navbar-expand-md navbar-light d-print-none" style={{ background: '#fff', borderBottom: '1px solid #e6e7e9' }}>
        <div className="container-xl">
          <Link to="/"><img src="/logo.png" alt="CMI · GLMEES" height="40"
            style={{ background: '#fff', borderRadius: 8, padding: 3 }} /></Link>
          <div className="ms-auto d-flex align-items-center gap-2">
            <span className="text-secondary d-none d-sm-inline">{user.name}</span>
            <button className="btn btn-sm" onClick={sair} disabled={logout.isPending}>Sair</button>
          </div>
        </div>
      </header>

      <div className="page-body">
        <div className="container-xl">
          {ok && <div className="alert alert-success">{ok}</div>}
          {error && <div className="alert alert-danger">{error.message}</div>}

          {/* Cabeçalho do perfil */}
          <div className="card mb-3">
            <div className="card-body d-flex align-items-center gap-3 flex-wrap">
              <span className="avatar avatar-xl" style={{
                backgroundImage: user.avatarUrl ? `url(${user.avatarUrl})` : undefined,
                background: user.avatarUrl ? undefined : 'var(--brand-blue)', color: '#fff', fontSize: '1.5rem',
              }}>
                {!user.avatarUrl && initials(user.name)}
              </span>
              <div>
                <h2 className="mb-0">{user.name}</h2>
                <div className="text-secondary">
                  {user.email} {user.emailVerified
                    ? <span className="badge bg-success text-white ms-1">verificado</span>
                    : <span className="badge bg-warning text-dark ms-1">não verificado</span>}
                </div>
                <label className="btn btn-sm mt-2 mb-0">
                  Trocar foto
                  <input type="file" hidden accept="image/jpeg,image/png,image/webp" onChange={enviarFoto} />
                </label>
              </div>
              <div className="ms-auto btn-list">
                <Link className="btn" to="/minha-conta/pedidos">Meus pedidos</Link>
                <Link className="btn" to="/minha-conta/ingressos">Meus ingressos</Link>
                <Link className="btn" to="/minha-conta/suporte">Suporte</Link>
              </div>
            </div>
          </div>

          {!user.emailVerified && (
            <div className="alert alert-warning">
              Confirme seu e-mail para o acesso completo.{' '}
              {resent ? 'E-mail reenviado — confira a caixa de entrada.'
                : <button className="btn btn-sm btn-warning ms-2" onClick={reenviar}>Reenviar confirmação</button>}
            </div>
          )}

          <div className="row row-cards">
            <div className="col-lg-6">
              <div className="card">
                <div className="card-header"><h3 className="card-title">Dados pessoais</h3></div>
                <div className="card-body">
                  <div className="mb-3">
                    <label className="form-label">Nome</label>
                    <input className="form-control" value={form.name} onChange={setForm('name')} />
                    {fieldError(error, 'name') && <div className="text-danger small">{fieldError(error, 'name')}</div>}
                  </div>
                  <div className="mb-3">
                    <label className="form-label">E-mail</label>
                    <input className="form-control" value={user.email} disabled />
                  </div>
                  <div className="row">
                    <div className="col-md-6 mb-3">
                      <label className="form-label">Telefone</label>
                      <input className="form-control" value={form.phone} onChange={setForm('phone')} placeholder="(00) 00000-0000" />
                    </div>
                    <div className="col-md-6 mb-3">
                      <label className="form-label">Documento</label>
                      <input className="form-control" value={form.document} onChange={setForm('document')} />
                    </div>
                  </div>
                  <button className="btn btn-primary" onClick={salvarDados}>Salvar dados</button>
                </div>
              </div>
            </div>

            <div className="col-lg-6">
              <div className="card">
                <div className="card-header"><h3 className="card-title">Alterar senha</h3></div>
                <div className="card-body">
                  {user.hasPassword && (
                    <div className="mb-3">
                      <label className="form-label">Senha atual</label>
                      <input type="password" className="form-control" value={pwd.current_password}
                        onChange={(e) => setPwd({ ...pwd, current_password: e.target.value })} autoComplete="current-password" />
                      {fieldError(error, 'current_password') && <div className="text-danger small">{fieldError(error, 'current_password')}</div>}
                    </div>
                  )}
                  <div className="mb-3">
                    <label className="form-label">Nova senha</label>
                    <input type="password" className="form-control" value={pwd.password}
                      onChange={(e) => setPwd({ ...pwd, password: e.target.value })} placeholder="Mínimo 8 caracteres" autoComplete="new-password" />
                    {fieldError(error, 'password') && <div className="text-danger small">{fieldError(error, 'password')}</div>}
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Confirme a nova senha</label>
                    <input type="password" className="form-control" value={pwd.password_confirmation}
                      onChange={(e) => setPwd({ ...pwd, password_confirmation: e.target.value })} autoComplete="new-password" />
                  </div>
                  <button className="btn btn-primary" onClick={salvarSenha}
                    disabled={!pwd.password || pwd.password !== pwd.password_confirmation}>
                    Alterar senha
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
