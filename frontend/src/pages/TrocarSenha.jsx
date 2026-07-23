import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../auth/AuthProvider'
import { apiPost } from '../lib/api'
import { parseApiError } from '../lib/forms'

/**
 * Troca obrigatória de senha no 1º acesso (contas geradas na compra, com senha
 * temporária). A navegação fica bloqueada aqui até definir uma senha própria.
 */
export default function TrocarSenha() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const submit = async (event) => {
    event.preventDefault()
    setError(null)
    if (password.length < 8) return setError({ message: 'A nova senha deve ter ao menos 8 caracteres.' })
    if (password !== confirm) return setError({ message: 'A confirmação não confere com a nova senha.' })

    setBusy(true)
    try {
      const me = await apiPost('/auth/password', { password, password_confirmation: confirm })
      queryClient.setQueryData(['auth', 'me'], me) // flag mustChangePassword limpa
      navigate('/minha-conta', { replace: true })
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="page page-center py-4" data-bs-theme="light" style={{ minHeight: '100vh' }}>
      <div className="container container-tight py-4">
        <div className="card card-md">
          <div className="card-body">
            <h2 className="h2 text-center mb-1">Defina sua senha</h2>
            <p className="text-secondary text-center mb-4">
              Sua conta foi criada com uma senha temporária. Por segurança, defina uma senha própria para continuar.
            </p>

            {error && <div className="alert alert-danger" role="alert">{error.message}</div>}

            <form onSubmit={submit} autoComplete="off">
              <div className="mb-3">
                <label className="form-label">Nova senha</label>
                <input type="password" className="form-control" value={password}
                  onChange={(e) => setPassword(e.target.value)} placeholder="Ao menos 8 caracteres" autoFocus />
              </div>
              <div className="mb-3">
                <label className="form-label">Confirmar nova senha</label>
                <input type="password" className="form-control" value={confirm}
                  onChange={(e) => setConfirm(e.target.value)} placeholder="Repita a nova senha" />
              </div>
              <div className="form-footer">
                <button type="submit" className="btn btn-primary w-100" disabled={busy}>
                  {busy ? 'Salvando…' : 'Salvar e continuar'}
                </button>
              </div>
            </form>
          </div>
        </div>

        <div className="text-center text-secondary mt-3">
          Conta: <strong>{user?.email}</strong> ·{' '}
          <a href="#" onClick={(e) => { e.preventDefault(); logout.mutate() }}>Sair</a>
        </div>
      </div>
    </div>
  )
}
