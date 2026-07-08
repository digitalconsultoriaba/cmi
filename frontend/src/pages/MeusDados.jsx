import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../auth/AuthProvider'
import { apiGet, apiPost, apiPut } from '../lib/api'
import { parseApiError, fieldError } from '../lib/forms'
import { maskWhatsapp } from '../lib/whatsapp'

export default function MeusDados() {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [error, setError] = useState(null)
  const [ok, setOk] = useState(null)
  const [profile, setProfile] = useState(null)
  const [pwd, setPwd] = useState({ current_password: '', password: '', password_confirmation: '' })

  // Inscrições do usuário — o vínculo (GLMEES / outra potência) vem da categoria
  // escolhida na inscrição, não de um seletor nesta tela.
  const { data: tickets = [] } = useQuery({ queryKey: ['my', 'tickets'], queryFn: () => apiGet('/tickets') })

  if (!user) return null

  const myTicket = tickets.find((t) => t.participantEmail?.toLowerCase() === user.email?.toLowerCase()) ?? tickets[0]
  const pf = myTicket?.participantFields ?? {}
  // Deriva o vínculo: categoria da inscrição > dado salvo no perfil.
  const vinculo = (myTicket?.participantCategoryKey ?? (user.potencia && !user.loja ? 'outra_potencia' : 'glmees')) === 'outra_potencia'
    ? 'outra' : 'glmees'

  const base = {
    name: user.name ?? '',
    phone: maskWhatsapp(user.phone || pf.whatsapp || ''),
    loja: user.loja || pf.loja || '',
    potencia: user.potencia || pf.potencia || '',
    cidade: user.cidade || pf.cidade || '',
    pais: user.pais || pf.pais || '',
    cargo_loja: user.cargoLoja || (vinculo === 'glmees' ? (pf.cargo || '') : ''),
    cargo_potencia: user.cargoPotencia || (vinculo === 'outra' ? (pf.cargo || '') : ''),
  }
  const form = profile ?? base
  const setForm = (f) => (e) => setProfile({ ...form, [f]: e.target.value })
  const refreshMe = () => queryClient.invalidateQueries({ queryKey: ['auth', 'me'] })

  const wrap = async (fn, msg) => {
    setError(null); setOk(null)
    try { await fn(); setOk(msg); refreshMe() } catch (err) { setError(parseApiError(err)) }
  }

  const salvarDados = () => {
    const payload = vinculo === 'glmees'
      ? { name: form.name, phone: form.phone, loja: form.loja, cargo_loja: form.cargo_loja,
          potencia: '', cidade: '', pais: '', cargo_potencia: '' }
      : { name: form.name, phone: form.phone, potencia: form.potencia, pais: form.pais,
          cidade: form.cidade, cargo_potencia: form.cargo_potencia, loja: '', cargo_loja: '' }
    return wrap(() => apiPut('/auth/profile', payload), 'Dados atualizados.')
  }

  const salvarSenha = () => wrap(async () => {
    await apiPost('/auth/password', pwd)
    setPwd({ current_password: '', password: '', password_confirmation: '' })
  }, 'Senha alterada.')

  const err = (f) => fieldError(error, f) && <div className="text-danger small">{fieldError(error, f)}</div>

  return (
    <>
      {ok && <div className="alert alert-success">{ok}</div>}
      <div className="row row-cards">
        <div className="col-lg-8">
          <div className="card">
            <div className="card-header d-flex align-items-center">
              <h3 className="card-title mb-0">Dados pessoais</h3>
              <span className="badge bg-blue-lt ms-2">
                {vinculo === 'glmees' ? 'Irmão da GLMEES' : 'Irmão de outra potência'}
              </span>
            </div>
            <div className="card-body">
              <div className="row">
                <div className="col-md-6 mb-3">
                  <label className="form-label">Nome</label>
                  <input className="form-control" value={form.name} onChange={setForm('name')} />
                  {err('name')}
                </div>
                <div className="col-md-6 mb-3">
                  <label className="form-label">WhatsApp</label>
                  <input className="form-control" inputMode="numeric" placeholder="55 (27) 99267-9890"
                    value={form.phone} onChange={(e) => setProfile({ ...form, phone: maskWhatsapp(e.target.value) })} />
                </div>
                <div className="col-md-6 mb-3">
                  <label className="form-label">E-mail</label>
                  <input className="form-control" value={user.email} disabled />
                </div>
              </div>

              {vinculo === 'glmees' ? (
                <div className="row">
                  <div className="col-md-8 mb-3">
                    <label className="form-label">Loja</label>
                    <input className="form-control" value={form.loja} onChange={setForm('loja')} />
                  </div>
                  <div className="col-md-4 mb-3">
                    <label className="form-label">Cargo (se houver)</label>
                    <input className="form-control" value={form.cargo_loja} onChange={setForm('cargo_loja')} />
                  </div>
                </div>
              ) : (
                <div className="row">
                  <div className="col-md-6 mb-3">
                    <label className="form-label">Potência</label>
                    <input className="form-control" value={form.potencia} onChange={setForm('potencia')} />
                  </div>
                  <div className="col-md-3 mb-3">
                    <label className="form-label">País</label>
                    <input className="form-control" value={form.pais} onChange={setForm('pais')} />
                  </div>
                  <div className="col-md-3 mb-3">
                    <label className="form-label">Cidade</label>
                    <input className="form-control" value={form.cidade} onChange={setForm('cidade')} />
                  </div>
                  <div className="col-md-6 mb-3">
                    <label className="form-label">Cargo (se houver)</label>
                    <input className="form-control" value={form.cargo_potencia} onChange={setForm('cargo_potencia')} />
                  </div>
                </div>
              )}

              <button className="btn btn-primary" onClick={salvarDados}>Salvar dados</button>
            </div>
          </div>
        </div>

        <div className="col-lg-4">
          <div className="card">
            <div className="card-header"><h3 className="card-title">Alterar senha</h3></div>
            <div className="card-body">
              {user.hasPassword && (
                <div className="mb-3">
                  <label className="form-label">Senha atual</label>
                  <input type="password" className="form-control" value={pwd.current_password}
                    onChange={(e) => setPwd({ ...pwd, current_password: e.target.value })} autoComplete="current-password" />
                  {err('current_password')}
                </div>
              )}
              <div className="mb-3">
                <label className="form-label">Nova senha</label>
                <input type="password" className="form-control" value={pwd.password}
                  onChange={(e) => setPwd({ ...pwd, password: e.target.value })} placeholder="Mínimo 8 caracteres" autoComplete="new-password" />
                {err('password')}
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
    </>
  )
}
