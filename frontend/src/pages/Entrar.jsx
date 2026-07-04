import { useState } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '../auth/AuthProvider'
import { apiGet } from '../lib/api'
import { parseApiError, fieldError } from '../lib/forms'

export default function Entrar() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [params] = useSearchParams()

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)

  const from = location.state?.from?.pathname ?? '/minha-conta'
  const verified = params.get('verified') === '1'
  const google = params.get('google')

  const submit = async (event) => {
    event.preventDefault()
    setError(null)
    try {
      await login.mutateAsync({ email, password })
      navigate(from, { replace: true })
    } catch (err) {
      setError(parseApiError(err))
    }
  }

  const loginGoogle = async () => {
    setError(null)
    try {
      const { url } = await apiGet('/auth/google/redirect')
      window.location.assign(url)
    } catch (err) {
      setError(parseApiError(err))
    }
  }

  return (
    <div className="page page-center py-4" data-bs-theme="light" style={{ minHeight: '100vh' }}>
      <div className="container container-tight py-4">
        <div className="text-center mb-4">
          <Link to="/">
            <img src="/logo.png" alt="CMI · GLMEES" style={{ height: 48, maxWidth: 220, objectFit: 'contain' }} />
          </Link>
        </div>

        <div className="card card-md">
          <div className="card-body">
            <h2 className="h2 text-center mb-4">Entrar na sua conta</h2>

            {verified && <div className="alert alert-success">E-mail confirmado! Faça login para continuar.</div>}
            {google === 'ok' && <div className="alert alert-success">Login com Google concluído. Redirecionando…</div>}
            {google === 'erro' && <div className="alert alert-danger">Não foi possível entrar com o Google. Tente novamente.</div>}
            {error && error.status !== 429 && !fieldError(error, 'email') && (
              <div className="alert alert-danger">{error.message}</div>
            )}
            {error?.status === 429 && <div className="alert alert-warning">{error.message}</div>}

            <form onSubmit={submit} autoComplete="on">
              <div className="mb-3">
                <label className="form-label">E-mail</label>
                <input type="email" className="form-control" placeholder="voce@email.com"
                  value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="email" />
                {fieldError(error, 'email') && <div className="text-danger small mt-1">{fieldError(error, 'email')}</div>}
              </div>
              <div className="mb-3">
                <label className="form-label d-flex justify-content-between">
                  <span>Senha</span>
                  <Link to="/esqueci-senha" className="small">Esqueci a senha</Link>
                </label>
                <input type="password" className="form-control" placeholder="Sua senha"
                  value={password} onChange={(e) => setPassword(e.target.value)} required autoComplete="current-password" />
              </div>
              <div className="form-footer">
                <button type="submit" className="btn btn-primary w-100" disabled={login.isPending}>
                  {login.isPending ? 'Entrando…' : 'Entrar'}
                </button>
              </div>
            </form>

            <div className="hr-text my-3">ou</div>

            <button type="button" className="btn w-100" onClick={loginGoogle}>
              Entrar com Google
            </button>
          </div>
        </div>

        <div className="text-center text-secondary mt-3">
          Não tem conta? <Link to="/cadastro">Cadastre-se</Link>
        </div>
      </div>
    </div>
  )
}
