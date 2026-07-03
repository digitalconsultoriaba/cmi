import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthProvider'
import { apiPost } from '../lib/api'
import { parseApiError } from '../lib/forms'

export default function MinhaConta() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [resent, setResent] = useState(false)
  const [error, setError] = useState(null)

  const sair = async () => {
    await logout.mutateAsync()
    navigate('/entrar', { replace: true })
  }

  const reenviar = async () => {
    setError(null)
    try {
      await apiPost('/auth/email/resend')
      setResent(true)
    } catch (err) {
      setError(parseApiError(err))
    }
  }

  if (!user) return null

  return (
    <main style={{ maxWidth: 560, margin: '4rem auto', fontFamily: 'sans-serif' }}>
      <h1>Minha conta</h1>

      <dl>
        <dt>Nome</dt>
        <dd>{user.name}</dd>
        <dt>E-mail</dt>
        <dd>
          {user.email} {user.emailVerified ? '✅ verificado' : '⚠️ não verificado'}
        </dd>
        <dt>Formas de entrada</dt>
        <dd>
          {[user.hasPassword && 'senha', user.hasGoogle && 'Google']
            .filter(Boolean)
            .join(' + ') || '—'}
        </dd>
        <dt>Perfis</dt>
        <dd>{user.roles.join(', ')}</dd>
      </dl>

      {!user.emailVerified && (
        <p>
          Confirme seu e-mail para garantir o acesso completo.{' '}
          {resent ? (
            <span role="status">E-mail reenviado — confira sua caixa de entrada.</span>
          ) : (
            <button type="button" onClick={reenviar}>Reenviar e-mail de confirmação</button>
          )}
          {error?.status === 429 && (
            <span role="alert"> Aguarde um instante antes de reenviar de novo.</span>
          )}
        </p>
      )}

      <button type="button" onClick={sair} disabled={logout.isPending}>
        {logout.isPending ? 'Saindo…' : 'Sair'}
      </button>
    </main>
  )
}
