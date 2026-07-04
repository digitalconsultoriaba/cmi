import { BrowserRouter, Link, Navigate, Route, Routes } from 'react-router-dom'
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
import Checkin from './admin/pages/Checkin'
import Entrar from './pages/Entrar'
import Cadastro from './pages/Cadastro'
import EsqueciSenha from './pages/EsqueciSenha'
import RedefinirSenha from './pages/RedefinirSenha'
import MinhaConta from './pages/MinhaConta'
import AdminLayout from './admin/AdminLayout'
import PagarPedido from './pages/PagarPedido'
import Tesouraria from './admin/pages/Tesouraria'
import TiposLotes from './admin/pages/TiposLotes'
import Camisas from './admin/pages/Camisas'
import Landing from './admin/pages/Landing'
import Cortesias from './admin/pages/Cortesias'
import Patrocinios from './admin/pages/Patrocinios'
import Auditoria from './admin/pages/Auditoria'
import Financeiro from './admin/pages/Financeiro'
// Painel v2 (spec 009) — casca em duas camadas de abas
import ModuloLayout from './admin/eventos/ModuloLayout'
import PainelModulo from './admin/eventos/PainelModulo'
import ListaEventos from './admin/eventos/ListaEventos'
import TiposEvento from './admin/eventos/TiposEvento'
import EventoLayout from './admin/eventos/EventoLayout'
import PainelEvento from './admin/eventos/abas/PainelEvento'
import Inscritos from './admin/eventos/abas/Inscritos'
import CheckinEvento from './admin/eventos/abas/CheckinEvento'
import Relatorios from './admin/eventos/abas/Relatorios'
import FinanceiroEvento from './admin/eventos/abas/FinanceiroEvento'
import Usuarios from './admin/eventos/Usuarios'

// Home do painel por papel: admin → módulo Eventos; tesouraria → Financeiro;
// portaria → Check-in direto
function PainelHome() {
  const { user } = useAuth()

  // Admin e financeiro entram no módulo inteiro (spec 009)
  if (user.roles.includes('admin') || user.roles.includes('treasury')) {
    return <Navigate to="/painel/modulo" replace />
  }

  return <Navigate to="/painel/checkin" replace />
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
              <RoleRoute roles={['admin', 'treasury', 'gate']}>
                <AdminLayout />
              </RoleRoute>
            }
          >
            <Route index element={<PainelHome />} />

            {/* ── Módulo Eventos e Ingressos (1ª camada de abas) ── */}
            <Route path="modulo" element={<RoleRoute roles={['admin', 'treasury']}><ModuloLayout /></RoleRoute>}>
              <Route index element={<PainelModulo />} />
              <Route path="eventos" element={<ListaEventos />} />
              <Route path="atendimentos" element={<SuporteFila />} />
              <Route path="tipos" element={<TiposEvento />} />
            </Route>

            {/* ── Usuários da equipe (módulo, admin) ── */}
            <Route path="usuarios" element={<RoleRoute role="admin"><Usuarios /></RoleRoute>} />

            {/* ── Dentro de um evento (2ª camada de abas) ── */}
            <Route path="eventos/:eventId" element={<RoleRoute roles={['admin', 'treasury']}><EventoLayout /></RoleRoute>}>
              <Route index element={<PainelEvento />} />
              <Route path="inscritos" element={<Inscritos />} />
              <Route path="ingressos" element={<TiposLotes />} />
              <Route path="camisas" element={<Camisas />} />
              <Route path="cortesias" element={<Cortesias />} />
              <Route path="patrocinio" element={<Patrocinios />} />
              <Route path="financeiro" element={<FinanceiroEvento />} />
              <Route path="atendimento" element={<SuporteFila />} />
              <Route path="relatorios" element={<Relatorios />} />
              <Route path="checkin" element={<CheckinEvento />} />
              <Route path="trilha" element={<Auditoria />} />
            </Route>

            {/* ── Rotas focadas (tesouraria/portaria) — sem regressão ── */}
            <Route path="tesouraria" element={<Tesouraria />} />
            <Route path="financeiro" element={<RoleRoute roles={['treasury', 'admin']}><Financeiro /></RoleRoute>} />
            <Route path="checkin" element={<Checkin />} />
            <Route path="landing/:eventId?" element={<RoleRoute role="admin"><Landing /></RoleRoute>} />
          </Route>
        </Routes>
        </CartProvider>
      </AuthProvider>
    </BrowserRouter>
  )
}
