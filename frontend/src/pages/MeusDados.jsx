import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../auth/AuthProvider'
import { apiPost, apiPut } from '../lib/api'
import { parseApiError, fieldError } from '../lib/forms'

const GRAUS = [
  { value: '', label: '—' },
  { value: 'aprendiz', label: 'Aprendiz' },
  { value: 'companheiro', label: 'Companheiro' },
  { value: 'mestre', label: 'Mestre' },
  { value: 'mestre_instalado', label: 'Mestre Instalado' },
]

export default function MeusDados() {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [error, setError] = useState(null)
  const [ok, setOk] = useState(null)
  const [profile, setProfile] = useState(null)
  const [pwd, setPwd] = useState({ current_password: '', password: '', password_confirmation: '' })

  if (!user) return null

  const base = {
    name: user.name ?? '', phone: user.phone ?? '', document: user.document ?? '',
    potencia: user.potencia ?? '', loja: user.loja ?? '', grau: user.grau ?? '',
    cargo_loja: user.cargoLoja ?? '', cargo_potencia: user.cargoPotencia ?? '',
    endereco: user.endereco ?? '', cidade: user.cidade ?? '', pais: user.pais ?? '',
  }
  const form = profile ?? base
  const setForm = (f) => (e) => setProfile({ ...form, [f]: e.target.value })
  const refreshMe = () => queryClient.invalidateQueries({ queryKey: ['auth', 'me'] })

  const wrap = async (fn, msg) => {
    setError(null); setOk(null)
    try { await fn(); setOk(msg); refreshMe() } catch (err) { setError(parseApiError(err)) }
  }
  const salvarDados = () => wrap(() => apiPut('/auth/profile', form), 'Dados atualizados.')
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
            <div className="card-header"><h3 className="card-title">Dados pessoais</h3></div>
            <div className="card-body">
              <div className="row">
                <div className="col-md-8 mb-3">
                  <label className="form-label">Nome</label>
                  <input className="form-control" value={form.name} onChange={setForm('name')} />
                  {err('name')}
                </div>
                <div className="col-md-4 mb-3">
                  <label className="form-label">Documento</label>
                  <input className="form-control" value={form.document} onChange={setForm('document')} />
                </div>
              </div>
              <div className="row">
                <div className="col-md-6 mb-3">
                  <label className="form-label">E-mail</label>
                  <input className="form-control" value={user.email} disabled />
                </div>
                <div className="col-md-6 mb-3">
                  <label className="form-label">Telefone</label>
                  <input className="form-control" value={form.phone} onChange={setForm('phone')} placeholder="(00) 00000-0000" />
                </div>
              </div>

              <hr />
              <div className="row">
                <div className="col-md-6 mb-3">
                  <label className="form-label">Potência</label>
                  <input className="form-control" value={form.potencia} onChange={setForm('potencia')} />
                </div>
                <div className="col-md-6 mb-3">
                  <label className="form-label">Loja</label>
                  <input className="form-control" value={form.loja} onChange={setForm('loja')} />
                </div>
                <div className="col-md-4 mb-3">
                  <label className="form-label">Grau</label>
                  <select className="form-select" value={form.grau} onChange={setForm('grau')}>
                    {GRAUS.map((g) => <option key={g.value} value={g.value}>{g.label}</option>)}
                  </select>
                </div>
                <div className="col-md-4 mb-3">
                  <label className="form-label">Cargo na Loja</label>
                  <input className="form-control" value={form.cargo_loja} onChange={setForm('cargo_loja')} />
                </div>
                <div className="col-md-4 mb-3">
                  <label className="form-label">Cargo na Potência</label>
                  <input className="form-control" value={form.cargo_potencia} onChange={setForm('cargo_potencia')} />
                </div>
              </div>

              <hr />
              <div className="row">
                <div className="col-md-6 mb-3">
                  <label className="form-label">Endereço</label>
                  <input className="form-control" value={form.endereco} onChange={setForm('endereco')} />
                </div>
                <div className="col-md-3 mb-3">
                  <label className="form-label">Cidade</label>
                  <input className="form-control" value={form.cidade} onChange={setForm('cidade')} />
                </div>
                <div className="col-md-3 mb-3">
                  <label className="form-label">País</label>
                  <input className="form-control" value={form.pais} onChange={setForm('pais')} />
                </div>
              </div>
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
