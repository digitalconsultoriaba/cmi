import { BrowserRouter, Link, Route, Routes } from 'react-router-dom'
import { AuthProvider, useAuth } from './auth/AuthProvider'
import ProtectedRoute from './auth/ProtectedRoute'
import RoleRoute from './auth/RoleRoute'
import Entrar from './pages/Entrar'
import Cadastro from './pages/Cadastro'
import EsqueciSenha from './pages/EsqueciSenha'
import RedefinirSenha from './pages/RedefinirSenha'
import MinhaConta from './pages/MinhaConta'
import AdminLayout from './admin/AdminLayout'
import Evento from './admin/pages/Evento'
import TiposLotes from './admin/pages/TiposLotes'
import Camisas from './admin/pages/Camisas'
import Landing from './admin/pages/Landing'
import Cortesias from './admin/pages/Cortesias'
import Patrocinios from './admin/pages/Patrocinios'

function Home() {
  const { user } = useAuth()

  return (
    <main style={{ fontFamily: 'sans-serif', padding: '2rem' }}>
      <h1>Plataforma de Eventos</h1>
      <p>A landing pública do evento chega na spec 004.</p>
      {user ? (
        <>
          <Link to="/minha-conta">Minha conta ({user.name})</Link>
          {user.roles.includes('admin') && <>{' · '}<Link to="/painel">Painel</Link></>}
        </>
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
          <Route
            path="/painel"
            element={
              <RoleRoute role="admin">
                <AdminLayout />
              </RoleRoute>
            }
          >
            <Route index element={<Evento />} />
            <Route path="tipos-lotes" element={<TiposLotes />} />
            <Route path="camisas" element={<Camisas />} />
            <Route path="landing" element={<Landing />} />
            <Route path="cortesias" element={<Cortesias />} />
            <Route path="patrocinios" element={<Patrocinios />} />
          </Route>
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  )
}
