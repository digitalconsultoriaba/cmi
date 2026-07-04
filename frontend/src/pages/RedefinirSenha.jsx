import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { apiPost } from '../lib/api'
import { parseApiError, fieldError } from '../lib/forms'

function Shell({ children }) {
  return (
    <div className="page page-center" data-bs-theme="light">
      <div className="container container-tight py-4">
        <div className="text-center mb-4">
          <Link to="/">
            <img src="/logo.png" alt="CMI · GLMEES" style={{ height: 48, maxWidth: 220, objectFit: 'contain' }} />
          </Link>
        </div>
        <div className="card card-md"><div className="card-body">{children}</div></div>
      </div>
    </div>
  )
}

export default function RedefinirSenha() {
  const [params] = useSearchParams()
  const navigate = useNavigate()

  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''

  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [error, setError] = useState(null)
  const [pending, setPending] = useState(false)

  const submit = async (event) => {
    event.preventDefault()
    setError(null)
    setPending(true)
    try {
      await apiPost('/auth/reset-password', {
        token, email, password, password_confirmation: confirmation,
      })
      navigate('/entrar', { replace: true })
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setPending(false)
    }
  }

  if (!token || !email) {
    return (
      <Shell>
        <div className="alert alert-danger">Link incompleto. Solicite uma nova redefinição.</div>
        <Link to="/esqueci-senha" className="btn w-100">Solicitar novamente</Link>
      </Shell>
    )
  }

  return (
    <Shell>
      <h2 className="h2 text-center mb-1">Definir nova senha</h2>
      <p className="text-secondary text-center mb-4">Conta: {email}</p>

      {error && !fieldError(error, 'password') && !fieldError(error, 'email') && (
        <div className="alert alert-danger">{error.message}</div>
      )}

      <form onSubmit={submit}>
        <div className="mb-3">
          <label className="form-label">Nova senha</label>
          <input type="password" className="form-control" placeholder="Mínimo 8 caracteres"
            value={password} onChange={(e) => setPassword(e.target.value)} required autoComplete="new-password" />
          {fieldError(error, 'password') && <div className="text-danger small mt-1">{fieldError(error, 'password')}</div>}
        </div>
        <div className="mb-3">
          <label className="form-label">Confirme a nova senha</label>
          <input type="password" className="form-control"
            value={confirmation} onChange={(e) => setConfirmation(e.target.value)} required autoComplete="new-password" />
        </div>
        {fieldError(error, 'email') && <div className="text-danger small mb-2">{fieldError(error, 'email')}</div>}
        <div className="form-footer">
          <button type="submit" className="btn btn-primary w-100" disabled={pending}>
            {pending ? 'Salvando…' : 'Salvar nova senha'}
          </button>
        </div>
      </form>
    </Shell>
  )
}
