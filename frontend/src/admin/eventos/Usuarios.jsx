import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut, apiDelete } from '../../lib/api'
import { ApiErrorAlert, Modal, useApiAction } from '../components'

const ROLE_LABEL = { admin: 'Administração', treasury: 'Financeiro', gate: 'Recepção (QR)' }
const ROLE_BADGE = { admin: 'bg-blue-lt', treasury: 'bg-green-lt', gate: 'bg-purple-lt' }

export default function Usuarios() {
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [creating, setCreating] = useState(false)
  const [search, setSearch] = useState('')

  const { data: users = [] } = useQuery({
    queryKey: ['admin', 'users'],
    queryFn: () => apiGet('/admin/users'),
  })

  const termo = search.trim().toLowerCase()
  const filtrados = termo
    ? users.filter((u) => `${u.name} ${u.email} ${u.roles.map((r) => ROLE_LABEL[r] ?? r).join(' ')}`
        .toLowerCase().includes(termo))
    : users

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['admin', 'users'] })

  const criar = (payload) => run(() => apiPost('/admin/users', payload),
    { onSuccess: () => { setCreating(false); refresh() } })

  const remover = (user) => run(() => apiDelete(`/admin/users/${user.id}`), { onSuccess: refresh })

  const [editing, setEditing] = useState(null)
  const salvarEdicao = (payload) => run(() => apiPut(`/admin/users/${editing.id}`, payload),
    { onSuccess: () => { setEditing(null); refresh() } })

  return (
    <>
      <div className="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
          <div className="page-pretitle">Equipe</div>
          <h2 className="page-title mb-0">Usuários</h2>
        </div>
        <button className="btn btn-primary" onClick={() => setCreating(true)}>Criar usuário</button>
      </div>

      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      {creating && <CriarUsuarioModal busy={busy} onClose={() => setCreating(false)} onCreate={criar} />}

      <div className="card">
        <div className="card-header d-flex align-items-center">
          <h3 className="card-title mb-0">Equipe ({filtrados.length})</h3>
          <div className="ms-auto">
            <input className="form-control" style={{ minWidth: 240 }} placeholder="Buscar por nome, e-mail ou papel…"
              value={search} onChange={(e) => setSearch(e.target.value)} />
          </div>
        </div>
        <div className="card-table table-responsive">
          <table className="table table-vcenter">
            <thead><tr><th>Nome</th><th>E-mail</th><th>Papel</th><th /></tr></thead>
            <tbody>
              {filtrados.map((u) => (
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
              {filtrados.length === 0 && <tr><td colSpan={4} className="text-secondary">
                {users.length === 0 ? 'Nenhum usuário de equipe.' : 'Nenhum usuário no filtro.'}
              </td></tr>}
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

function CriarUsuarioModal({ busy, onClose, onCreate }) {
  const [f, setF] = useState({ name: '', email: '', password: '', role: 'gate' })
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value })
  const valido = f.name.trim() && f.email.trim() && f.password.length >= 8

  return (
    <Modal title="Criar usuário" size="sm" onClose={onClose}
      footer={<>
        <button className="btn" onClick={onClose}>Cancelar</button>
        <button className="btn btn-primary" disabled={busy || !valido} onClick={() => onCreate(f)}>Salvar</button>
      </>}>
      <div className="mb-3">
        <label className="form-label required">Nome</label>
        <input className="form-control" autoFocus value={f.name} onChange={set('name')} />
      </div>
      <div className="mb-3">
        <label className="form-label required">E-mail</label>
        <input type="email" className="form-control" value={f.email} onChange={set('email')} />
      </div>
      <div className="mb-3">
        <label className="form-label required">Papel</label>
        <select className="form-select" value={f.role} onChange={set('role')}>
          <option value="gate">Recepção (QR)</option>
          <option value="treasury">Financeiro</option>
          <option value="admin">Administração</option>
        </select>
      </div>
      <div className="mb-1">
        <label className="form-label required">Senha inicial</label>
        <input type="text" className="form-control" placeholder="Mínimo 8 caracteres"
          value={f.password} onChange={set('password')} />
        <small className="form-hint">A pessoa entra com esse e-mail e senha.</small>
      </div>
    </Modal>
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
