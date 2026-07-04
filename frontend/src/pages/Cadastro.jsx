import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthProvider'
import { parseApiError, fieldError } from '../lib/forms'

export default function Cadastro() {
  const { register } = useAuth()
  const navigate = useNavigate()

  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [error, setError] = useState(null)

  const set = (field) => (event) => setForm({ ...form, [field]: event.target.value })

  const submit = async (event) => {
    event.preventDefault()
    setError(null)
    try {
      await register.mutateAsync(form)
      navigate('/minha-conta', { replace: true })
    } catch (err) {
      setError(parseApiError(err))
    }
  }

  return (
    <div className="page page-center" data-bs-theme="light">
      <div className="container container-tight py-4">
        <div className="text-center mb-4">
          <Link to="/">
            <img src="/logo.png" alt="CMI · GLMEES" style={{ height: 48, maxWidth: 220, objectFit: 'contain' }} />
          </Link>
        </div>

        <div className="card card-md">
          <div className="card-body">
            <h2 className="h2 text-center mb-4">Criar sua conta</h2>

            {error && !fieldError(error, 'name') && !fieldError(error, 'email') && !fieldError(error, 'password') && (
              <div className="alert alert-danger">{error.message}</div>
            )}

            <form onSubmit={submit} autoComplete="on">
              <div className="mb-3">
                <label className="form-label">Nome</label>
                <input className="form-control" value={form.name} onChange={set('name')} required autoComplete="name" />
                {fieldError(error, 'name') && <div className="text-danger small mt-1">{fieldError(error, 'name')}</div>}
              </div>
              <div className="mb-3">
                <label className="form-label">E-mail</label>
                <input type="email" className="form-control" placeholder="voce@email.com"
                  value={form.email} onChange={set('email')} required autoComplete="email" />
                {fieldError(error, 'email') && <div className="text-danger small mt-1">{fieldError(error, 'email')}</div>}
              </div>
              <div className="mb-3">
                <label className="form-label">Senha</label>
                <input type="password" className="form-control" placeholder="Mínimo 8 caracteres"
                  value={form.password} onChange={set('password')} required autoComplete="new-password" />
                {fieldError(error, 'password') && <div className="text-danger small mt-1">{fieldError(error, 'password')}</div>}
              </div>
              <div className="mb-3">
                <label className="form-label">Confirme a senha</label>
                <input type="password" className="form-control"
                  value={form.password_confirmation} onChange={set('password_confirmation')} required autoComplete="new-password" />
              </div>
              <div className="form-footer">
                <button type="submit" className="btn btn-primary w-100" disabled={register.isPending}>
                  {register.isPending ? 'Criando…' : 'Criar conta'}
                </button>
              </div>
            </form>
          </div>
        </div>

        <div className="text-center text-secondary mt-3">
          Já tem conta? <Link to="/entrar">Entrar</Link>
        </div>
      </div>
    </div>
  )
}
