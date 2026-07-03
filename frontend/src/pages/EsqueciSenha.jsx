import { useState } from 'react'
import { Link } from 'react-router-dom'
import { apiPost } from '../lib/api'
import { parseApiError } from '../lib/forms'

export default function EsqueciSenha() {
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(false)
  const [error, setError] = useState(null)
  const [pending, setPending] = useState(false)

  const submit = async (event) => {
    event.preventDefault()
    setError(null)
    setPending(true)
    try {
      await apiPost('/auth/forgot-password', { email })
      setSent(true) // resposta é idêntica exista ou não a conta
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setPending(false)
    }
  }

  return (
    <main style={{ maxWidth: 420, margin: '4rem auto', fontFamily: 'sans-serif' }}>
      <h1>Esqueci minha senha</h1>

      {sent ? (
        <p role="status">
          Se este e-mail estiver cadastrado, você receberá um link para definir uma
          nova senha. Verifique também a caixa de spam.
        </p>
      ) : (
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
          {error && <p role="alert">{error.message}</p>}
          <button type="submit" disabled={pending}>
            {pending ? 'Enviando…' : 'Enviar link'}
          </button>
        </form>
      )}

      <p>
        <Link to="/entrar">Voltar ao login</Link>
      </p>
    </main>
  )
}
