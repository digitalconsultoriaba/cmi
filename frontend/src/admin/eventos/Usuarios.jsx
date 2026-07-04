import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut, apiDelete } from '../../lib/api'
import { ApiErrorAlert, useApiAction } from '../components'

const ROLE_LABEL = { admin: 'Administração', treasury: 'Financeiro', gate: 'Recepção (QR)' }
const ROLE_BADGE = { admin: 'bg-blue-lt', treasury: 'bg-green-lt', gate: 'bg-purple-lt' }

const EMPTY = { name: '', email: '', password: '', role: 'gate' }

export default function Usuarios() {
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [form, setForm] = useState(EMPTY)

  const { data: users = [] } = useQuery({
    queryKey: ['admin', 'users'],
    queryFn: () => apiGet('/admin/users'),
  })

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['admin', 'users'] })
  const set = (f) => (e) => setForm({ ...form, [f]: e.target.value })

  const criar = () => run(() => apiPost('/admin/users', form),
    { onSuccess: () => { setForm(EMPTY); refresh() } })

  const trocarPapel = (user, role) => run(() => apiPut(`/admin/users/${user.id}`, { role }),
    { onSuccess: refresh })

  const remover = (user) => run(() => apiDelete(`/admin/users/${user.id}`), { onSuccess: refresh })

  const [editing, setEditing] = useState(null)
  const salvarEdicao = (payload) => run(() => apiPut(`/admin/users/${editing.id}`, payload),
    { onSuccess: () => { setEditing(null); refresh() } })

  return (
    <>
      <div className="page-header d-print-none">
        <div className="page-pretitle">Equipe</div>
        <h2 className="page-title">Usuários</h2>
      </div>

      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <div className="card mb-3">
        <div className="card-header"><h3 className="card-title">Novo usuário da equipe</h3></div>
        <div className="card-body">
          <div className="row g-2 align-items-end">
            <div className="col-md-3">
              <label className="form-label">Nome</label>
              <input className="form-control" value={form.name} onChange={set('name')} />
            </div>
            <div className="col-md-3">
              <label className="form-label">E-mail</label>
              <input type="email" className="form-control" value={form.email} onChange={set('email')} />
            </div>
            <div className="col-md-2">
              <label className="form-label">Senha inicial</label>
              <input type="text" className="form-control" value={form.password} onChange={set('password')} />
            </div>
            <div className="col-md-2">
              <label className="form-label">Papel</label>
              <select className="form-select" value={form.role} onChange={set('role')}>
                <option value="gate">Recepção (QR)</option>
                <option value="treasury">Financeiro</option>
                <option value="admin">Administração</option>
              </select>
            </div>
            <div className="col-md-2">
              <button className="btn btn-primary w-100" onClick={criar}
                disabled={busy || !form.name.trim() || !form.email.trim() || form.password.length < 8}>
                Criar usuário
              </button>
            </div>
          </div>
          <small className="form-hint">A pessoa entra com esse e-mail e senha. Senha mínima de 8 caracteres.</small>
        </div>
      </div>

      <div className="card">
        <div className="card-header"><h3 className="card-title">Equipe ({users.length})</h3></div>
        <div className="card-table table-responsive">
          <table className="table table-vcenter">
            <thead><tr><th>Nome</th><th>E-mail</th><th>Papel</th><th /></tr></thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id}>
                  <td className="fw-bold">{u.name}</td>
                  <td>{u.email}</td>
                  <td>
                    {u.roles.map((r) => (
                      <span key={r} className={`badge ${ROLE_BADGE[r] ?? 'bg-secondary'} text-white`}>{ROLE_LABEL[r] ?? r}</span>
                    ))}
                  </td>
                  <td className="text-end">
                    <span className="btn-list justify-content-end">
                      <button className="btn btn-sm" onClick={() => setEditing(u)}>Editar</button>
                      <button className="btn btn-sm btn-outline-danger" onClick={() => remover(u)}>Remover</button>
                    </span>
                  </td>
                </tr>
              ))}
              {users.length === 0 && <tr><td colSpan={4} className="text-secondary">Nenhum usuário de equipe.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>

      {editing && (
        <EditarUsuarioModal user={editing} busy={busy}
          onClose={() => setEditing(null)} onSave={salvarEdicao} />
      )}
    </>
  )
}

function EditarUsuarioModal({ user, busy, onClose, onSave }) {
  const [name, setName] = useState(user.name)
  const [role, setRole] = useState(user.roles[0] ?? 'gate')
  const [password, setPassword] = useState('')

  const salvar = () => {
    const payload = { name, role }
    if (password.trim()) payload.password = password
    onSave(payload)
  }

  return (
    <div className="modal modal-blur fade show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,.4)' }}>
      <div className="modal-dialog modal-sm" role="document">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Editar usuário</h5>
            <button type="button" className="btn-close" onClick={onClose} />
          </div>
          <div className="modal-body">
            <div className="mb-3">
              <label className="form-label">Nome</label>
              <input className="form-control" value={name} onChange={(e) => setName(e.target.value)} />
            </div>
            <div className="mb-3">
              <label className="form-label">E-mail</label>
              <input className="form-control" value={user.email} disabled />
            </div>
            <div className="mb-3">
              <label className="form-label">Papel</label>
              <select className="form-select" value={role} onChange={(e) => setRole(e.target.value)}>
                <option value="gate">Recepção (QR)</option>
                <option value="treasury">Financeiro</option>
                <option value="admin">Administração</option>
              </select>
            </div>
            <div className="mb-1">
              <label className="form-label">Nova senha (opcional)</label>
              <input type="text" className="form-control" placeholder="Deixe em branco para manter"
                value={password} onChange={(e) => setPassword(e.target.value)} />
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn" onClick={onClose}>Cancelar</button>
            <button className="btn btn-primary" disabled={busy || !name.trim()} onClick={salvar}>Salvar</button>
          </div>
        </div>
      </div>
    </div>
  )
}
