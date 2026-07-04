import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut, apiDelete } from '../../lib/api'
import { ApiErrorAlert, useApiAction } from '../components'

export default function Cadastros() {
  const [tab, setTab] = useState('categorias')
  return (
    <>
      <div className="btn-group mb-3">
        {[['categorias', 'Categorias'], ['pessoas', 'Fornecedores / Clientes'], ['formas', 'Formas de pagamento']].map(([v, l]) => (
          <button key={v} className={`btn ${tab === v ? 'btn-primary' : 'btn-outline-primary'}`} onClick={() => setTab(v)}>{l}</button>
        ))}
      </div>
      {tab === 'categorias' && <Categorias />}
      {tab === 'pessoas' && <Pessoas />}
      {tab === 'formas' && <Formas />}
    </>
  )
}

function Categorias() {
  const qc = useQueryClient()
  const { run, error, setError } = useApiAction()
  const [form, setForm] = useState({ direction: 'expense', name: '' })
  const { data: cats = [] } = useQuery({ queryKey: ['finance', 'categories', 'all'], queryFn: () => apiGet('/finance/categories') })
  const refresh = () => qc.invalidateQueries({ queryKey: ['finance', 'categories'] })

  return (
    <div className="card">
      <div className="card-header"><h3 className="card-title">Categorias financeiras</h3></div>
      <div className="card-body">
        <ApiErrorAlert error={error} onClose={() => setError(null)} />
        <div className="row g-2 mb-3">
          <div className="col-md-3"><select className="form-select" value={form.direction} onChange={(e) => setForm({ ...form, direction: e.target.value })}>
            <option value="income">Receita</option><option value="expense">Despesa</option></select></div>
          <div className="col-md-5"><input className="form-control" placeholder="Nome" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></div>
          <div className="col-md-2"><button className="btn btn-primary" disabled={!form.name.trim()}
            onClick={() => run(() => apiPost('/finance/categories', form), { onSuccess: () => { setForm({ ...form, name: '' }); refresh() } })}>Adicionar</button></div>
        </div>
        <table className="table table-vcenter"><thead><tr><th>Tipo</th><th>Nome</th><th>Situação</th><th /></tr></thead>
          <tbody>{cats.map((c) => (
            <tr key={c.id}>
              <td>{c.direction === 'income' ? 'Receita' : 'Despesa'}</td><td>{c.name}</td>
              <td>{c.isActive ? <span className="badge bg-success text-white">ativa</span> : <span className="badge bg-secondary text-white">inativa</span>}</td>
              <td className="text-end btn-list justify-content-end">
                <button className="btn btn-sm" onClick={() => run(() => apiPut(`/finance/categories/${c.id}`, { is_active: !c.isActive }), { onSuccess: refresh })}>{c.isActive ? 'Inativar' : 'Ativar'}</button>
                <button className="btn btn-sm btn-outline-danger" onClick={() => run(() => apiDelete(`/finance/categories/${c.id}`), { onSuccess: refresh })}>Excluir</button>
              </td>
            </tr>
          ))}</tbody>
        </table>
      </div>
    </div>
  )
}

function Pessoas() {
  const qc = useQueryClient()
  const { run, error, setError } = useApiAction()
  const [form, setForm] = useState({ kind: 'supplier', name: '', document: '', phone: '', email: '' })
  const { data: people = [] } = useQuery({ queryKey: ['finance', 'people'], queryFn: () => apiGet('/finance/people') })
  const refresh = () => qc.invalidateQueries({ queryKey: ['finance', 'people'] })
  const KIND = { supplier: 'Fornecedor', customer: 'Cliente', sponsor: 'Patrocinador', participant: 'Participante', provider: 'Prestador', other: 'Outro' }

  return (
    <div className="card">
      <div className="card-header"><h3 className="card-title">Fornecedores / Clientes</h3></div>
      <div className="card-body">
        <ApiErrorAlert error={error} onClose={() => setError(null)} />
        <div className="row g-2 mb-3">
          <div className="col-md-2"><select className="form-select" value={form.kind} onChange={(e) => setForm({ ...form, kind: e.target.value })}>
            {Object.entries(KIND).map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select></div>
          <div className="col-md-3"><input className="form-control" placeholder="Nome" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></div>
          <div className="col-md-2"><input className="form-control" placeholder="CPF/CNPJ" value={form.document} onChange={(e) => setForm({ ...form, document: e.target.value })} /></div>
          <div className="col-md-2"><input className="form-control" placeholder="Telefone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></div>
          <div className="col-md-3 d-flex gap-2"><input className="form-control" placeholder="E-mail" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
            <button className="btn btn-primary" disabled={!form.name.trim()} onClick={() => run(() => apiPost('/finance/people', form), { onSuccess: () => { setForm({ ...form, name: '', document: '', phone: '', email: '' }); refresh() } })}>+</button></div>
        </div>
        <table className="table table-vcenter"><thead><tr><th>Tipo</th><th>Nome</th><th>Documento</th><th>Contato</th><th /></tr></thead>
          <tbody>{people.map((p) => (
            <tr key={p.id}><td>{KIND[p.kind] ?? p.kind}</td><td>{p.name}</td><td>{p.document ?? '—'}</td>
              <td className="small">{[p.phone, p.email].filter(Boolean).join(' · ') || '—'}</td>
              <td className="text-end"><button className="btn btn-sm btn-outline-danger" onClick={() => run(() => apiDelete(`/finance/people/${p.id}`), { onSuccess: refresh })}>Excluir</button></td>
            </tr>
          ))}</tbody>
        </table>
      </div>
    </div>
  )
}

function Formas() {
  const qc = useQueryClient()
  const { run } = useApiAction()
  const [name, setName] = useState('')
  const { data: methods = [] } = useQuery({ queryKey: ['finance', 'methods'], queryFn: () => apiGet('/finance/payment-methods') })
  const refresh = () => qc.invalidateQueries({ queryKey: ['finance', 'methods'] })
  return (
    <div className="card">
      <div className="card-header"><h3 className="card-title">Formas de pagamento</h3></div>
      <div className="card-body">
        <div className="row g-2 mb-3">
          <div className="col-md-4"><input className="form-control" placeholder="Nova forma" value={name} onChange={(e) => setName(e.target.value)} /></div>
          <div className="col-md-2"><button className="btn btn-primary" disabled={!name.trim()} onClick={() => run(() => apiPost('/finance/payment-methods', { name }), { onSuccess: () => { setName(''); refresh() } })}>Adicionar</button></div>
        </div>
        <div className="d-flex flex-wrap gap-2">
          {methods.map((m) => <span key={m.id} className={`badge ${m.isActive ? 'bg-primary' : 'bg-secondary'} text-white`}>{m.name}</span>)}
        </div>
      </div>
    </div>
  )
}
