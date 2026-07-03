import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { apiPost } from '../lib/api'
import { parseApiError, fieldError } from '../lib/forms'

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
        token,
        email,
        password,
        password_confirmation: confirmation,
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
      <main style={{ maxWidth: 420, margin: '4rem auto', fontFamily: 'sans-serif' }}>
        <p role="alert">Link incompleto. Solicite uma nova redefinição.</p>
        <Link to="/esqueci-senha">Solicitar novamente</Link>
      </main>
    )
  }

  return (
    <main style={{ maxWidth: 420, margin: '4rem auto', fontFamily: 'sans-serif' }}>
      <h1>Definir nova senha</h1>
      <p>Conta: {email}</p>

      <form onSubmit={submit}>
        <label>
          Nova senha (mínimo 8 caracteres)
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            autoComplete="new-password"
          />
        </label>
        {fieldError(error, 'password') && <p role="alert">{fieldError(error, 'password')}</p>}

        <label>
          Confirme a nova senha
          <input
            type="password"
            value={confirmation}
            onChange={(e) => setConfirmation(e.target.value)}
            required
            autoComplete="new-password"
          />
        </label>

        {fieldError(error, 'email') && <p role="alert">{fieldError(error, 'email')}</p>}

        <button type="submit" disabled={pending}>
          {pending ? 'Salvando…' : 'Salvar nova senha'}
        </button>
      </form>
    </main>
  )
}
