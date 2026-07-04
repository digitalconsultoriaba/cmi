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
    <div className="page page-center" data-bs-theme="light">
      <div className="container container-tight py-4">
        <div className="text-center mb-4">
          <Link to="/">
            <img src="/logo.png" alt="CMI · GLMEES" style={{ height: 48, maxWidth: 220, objectFit: 'contain' }} />
          </Link>
        </div>

        <div className="card card-md">
          <div className="card-body">
            <h2 className="h2 text-center mb-3">Esqueci minha senha</h2>

            {sent ? (
              <div className="alert alert-success mb-0">
                Se este e-mail estiver cadastrado, você receberá um link para definir
                uma nova senha. Verifique também a caixa de spam.
              </div>
            ) : (
              <>
                <p className="text-secondary mb-4">
                  Informe o e-mail da sua conta e enviaremos um link para redefinir a senha.
                </p>
                {error && <div className="alert alert-danger">{error.message}</div>}
                <form onSubmit={submit}>
                  <div className="mb-3">
                    <label className="form-label">E-mail</label>
                    <input type="email" className="form-control" placeholder="voce@email.com"
                      value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="email" />
                  </div>
                  <div className="form-footer">
                    <button type="submit" className="btn btn-primary w-100" disabled={pending}>
                      {pending ? 'Enviando…' : 'Enviar link de recuperação'}
                    </button>
                  </div>
                </form>
              </>
            )}
          </div>
        </div>

        <div className="text-center text-secondary mt-3">
          <Link to="/entrar">← Voltar ao login</Link>
        </div>
      </div>
    </div>
  )
}
