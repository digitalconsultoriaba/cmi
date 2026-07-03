import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthProvider'
import { parseApiError, fieldError } from '../lib/forms'

export default function Cadastro() {
  const { register } = useAuth()
  const navigate = useNavigate()

  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  })
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
    <main style={{ maxWidth: 420, margin: '4rem auto', fontFamily: 'sans-serif' }}>
      <h1>Criar conta</h1>

      <form onSubmit={submit}>
        <label>
          Nome
          <input value={form.name} onChange={set('name')} required autoComplete="name" />
        </label>
        {fieldError(error, 'name') && <p role="alert">{fieldError(error, 'name')}</p>}

        <label>
          E-mail
          <input
            type="email"
            value={form.email}
            onChange={set('email')}
            required
            autoComplete="email"
          />
        </label>
        {fieldError(error, 'email') && <p role="alert">{fieldError(error, 'email')}</p>}

        <label>
          Senha (mínimo 8 caracteres)
          <input
            type="password"
            value={form.password}
            onChange={set('password')}
            required
            autoComplete="new-password"
          />
        </label>
        {fieldError(error, 'password') && <p role="alert">{fieldError(error, 'password')}</p>}

        <label>
          Confirme a senha
          <input
            type="password"
            value={form.password_confirmation}
            onChange={set('password_confirmation')}
            required
            autoComplete="new-password"
          />
        </label>

        <button type="submit" disabled={register.isPending}>
          {register.isPending ? 'Criando…' : 'Criar conta'}
        </button>
      </form>

      <p>
        Já tem conta? <Link to="/entrar">Entrar</Link>
      </p>
    </main>
  )
}
