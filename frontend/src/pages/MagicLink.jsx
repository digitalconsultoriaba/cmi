import { useState } from 'react'
import { apiPost } from '../lib/api'

/**
 * Página de reenvio de acesso (magic link). O consumo do link acontece direto
 * na rota web do backend (/auth/magic/{user}) → sessão + redirect. Aqui o
 * usuário pede um novo link caso o dele tenha expirado.
 */
export default function MagicLink() {
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(false)

  const request = async () => {
    if (!email.trim()) return
    await apiPost('/auth/magic/request', { email: email.trim() })
    setSent(true)
  }

  return (
    <div className="container py-5" style={{ maxWidth: 420 }}>
      <h1 className="h4">Acessar minha conta</h1>
      <p className="text-secondary">Informe seu e-mail e enviaremos um link de acesso (sem senha).</p>
      {sent
        ? <div className="alert alert-success">Se houver uma conta com este e-mail, enviamos um link de acesso.</div>
        : (
          <>
            <input type="email" className="form-control mb-2" placeholder="seu@email.com" value={email} onChange={(e) => setEmail(e.target.value)} />
            <button className="btn btn-primary w-100" onClick={request}>Enviar link de acesso</button>
          </>
        )}
    </div>
  )
}
