import { BrowserRouter, Link, Route, Routes } from 'react-router-dom'
import { AuthProvider, useAuth } from './auth/AuthProvider'
import ProtectedRoute from './auth/ProtectedRoute'
import Entrar from './pages/Entrar'
import Cadastro from './pages/Cadastro'
import EsqueciSenha from './pages/EsqueciSenha'
import RedefinirSenha from './pages/RedefinirSenha'
import MinhaConta from './pages/MinhaConta'

function Home() {
  const { user } = useAuth()

  return (
    <main style={{ fontFamily: 'sans-serif', padding: '2rem' }}>
      <h1>Plataforma de Eventos</h1>
      <p>A landing pública do evento chega na spec 004.</p>
      {user ? (
        <Link to="/minha-conta">Minha conta ({user.name})</Link>
      ) : (
        <Link to="/entrar">Entrar</Link>
      )}
    </main>
  )
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/entrar" element={<Entrar />} />
          <Route path="/cadastro" element={<Cadastro />} />
          <Route path="/esqueci-senha" element={<EsqueciSenha />} />
          <Route path="/redefinir-senha" element={<RedefinirSenha />} />
          <Route
            path="/minha-conta"
            element={
              <ProtectedRoute>
                <MinhaConta />
              </ProtectedRoute>
            }
          />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  )
}
