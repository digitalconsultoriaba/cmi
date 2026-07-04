import { BrowserRouter, Link, Route, Routes } from 'react-router-dom'
import { AuthProvider, useAuth } from './auth/AuthProvider'
import ProtectedRoute from './auth/ProtectedRoute'
import RoleRoute from './auth/RoleRoute'
import { CartProvider } from './cart/CartProvider'
import EventoPublico from './pages/EventoPublico'
import Checkout from './pages/Checkout'
import MeusPedidos from './pages/MeusPedidos'
import MeusIngressos from './pages/MeusIngressos'
import Suporte from './pages/Suporte'
import SuporteFila from './admin/pages/SuporteFila'
import Entrar from './pages/Entrar'
import Cadastro from './pages/Cadastro'
import EsqueciSenha from './pages/EsqueciSenha'
import RedefinirSenha from './pages/RedefinirSenha'
import MinhaConta from './pages/MinhaConta'
import AdminLayout from './admin/AdminLayout'
import PagarPedido from './pages/PagarPedido'
import Tesouraria from './admin/pages/Tesouraria'
import Evento from './admin/pages/Evento'
import TiposLotes from './admin/pages/TiposLotes'
import Camisas from './admin/pages/Camisas'
import Landing from './admin/pages/Landing'
import Cortesias from './admin/pages/Cortesias'
import Patrocinios from './admin/pages/Patrocinios'

// Home do painel: admin vê o Evento; tesouraria-só cai direto na Tesouraria
function PainelHome() {
  const { user } = useAuth()

  return user.roles.includes('admin') ? <Evento /> : <Tesouraria />
}

function Home() {
  const { user } = useAuth()

  return (
    <main style={{ fontFamily: 'sans-serif', padding: '2rem' }}>
      <h1>Plataforma de Eventos</h1>
      <p>
        <Link to="/evento/seminario-internacional-2026">Ver o evento →</Link>
      </p>
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
        <CartProvider>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/entrar" element={<Entrar />} />
          <Route path="/cadastro" element={<Cadastro />} />
          <Route path="/esqueci-senha" element={<EsqueciSenha />} />
          <Route path="/redefinir-senha" element={<RedefinirSenha />} />
          <Route path="/evento/:slug" element={<EventoPublico />} />
          <Route
            path="/checkout"
            element={
              <ProtectedRoute>
                <Checkout />
              </ProtectedRoute>
            }
          />
          <Route
            path="/pedido/:code/pagar"
            element={
              <ProtectedRoute>
                <PagarPedido />
              </ProtectedRoute>
            }
          />
          <Route
            path="/minha-conta/pedidos"
            element={
              <ProtectedRoute>
                <MeusPedidos />
              </ProtectedRoute>
            }
          />
          <Route
            path="/minha-conta/ingressos"
            element={
              <ProtectedRoute>
                <MeusIngressos />
              </ProtectedRoute>
            }
          />
          <Route
            path="/minha-conta/suporte"
            element={
              <ProtectedRoute>
                <Suporte />
              </ProtectedRoute>
            }
          />
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
              <RoleRoute roles={['admin', 'treasury']}>
                <AdminLayout />
              </RoleRoute>
            }
          >
            <Route index element={<PainelHome />} />
            <Route path="tipos-lotes" element={<RoleRoute role="admin"><TiposLotes /></RoleRoute>} />
            <Route path="camisas" element={<RoleRoute role="admin"><Camisas /></RoleRoute>} />
            <Route path="landing" element={<RoleRoute role="admin"><Landing /></RoleRoute>} />
            <Route path="cortesias" element={<RoleRoute role="admin"><Cortesias /></RoleRoute>} />
            <Route path="patrocinios" element={<RoleRoute role="admin"><Patrocinios /></RoleRoute>} />
            <Route path="tesouraria" element={<Tesouraria />} />
            <Route path="suporte" element={<SuporteFila />} />
          </Route>
        </Routes>
        </CartProvider>
      </AuthProvider>
    </BrowserRouter>
  )
}
