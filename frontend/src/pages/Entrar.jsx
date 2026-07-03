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
    <main style={{ maxWidth: 420, margin: '4rem auto', fontFamily: 'sans-serif' }}>
      <h1>Entrar</h1>

      {verified && <p role="status">✅ E-mail confirmado! Faça login para continuar.</p>}
      {google === 'ok' && <p role="status">✅ Login com Google concluído. Redirecionando…</p>}
      {google === 'erro' && (
        <p role="alert">Não foi possível entrar com o Google. Tente novamente.</p>
      )}
      {error?.status === 429 && <p role="alert">{error.message}</p>}
      {error && error.status !== 429 && !fieldError(error, 'email') && (
        <p role="alert">{error.message}</p>
      )}

      <form onSubmit={submit}>
        <label>
          E-mail
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            autoComplete="email"
          />
        </label>
        {fieldError(error, 'email') && <p role="alert">{fieldError(error, 'email')}</p>}

        <label>
          Senha
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            autoComplete="current-password"
          />
        </label>

        <button type="submit" disabled={login.isPending}>
          {login.isPending ? 'Entrando…' : 'Entrar'}
        </button>
      </form>

      <button type="button" onClick={loginGoogle}>Entrar com Google</button>

      <p>
        <Link to="/esqueci-senha">Esqueci minha senha</Link>
      </p>
      <p>
        Não tem conta? <Link to="/cadastro">Cadastre-se</Link>
      </p>
    </main>
  )
}
