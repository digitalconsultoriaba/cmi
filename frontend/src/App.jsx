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
import Atendimentos from './admin/pages/Atendimentos'
import Checkin from './admin/pages/Checkin'
import PortariaEventos from './admin/pages/PortariaEventos'
import Entrar from './pages/Entrar'
import Cadastro from './pages/Cadastro'
import EsqueciSenha from './pages/EsqueciSenha'
import RedefinirSenha from './pages/RedefinirSenha'
import MinhaConta from './pages/MinhaConta'
import MeusDados from './pages/MeusDados'
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
import PainelModulo from './admin/eventos/PainelModulo'
import ListaEventos from './admin/eventos/ListaEventos'
import EventoLayout from './admin/eventos/EventoLayout'
import PainelEvento from './admin/eventos/abas/PainelEvento'
import Inscritos from './admin/eventos/abas/Inscritos'
import CheckinEvento from './admin/eventos/abas/CheckinEvento'
import Relatorios from './admin/eventos/abas/Relatorios'
import Orcamento from './admin/eventos/abas/Orcamento'
import Inscricoes from './admin/eventos/abas/Inscricoes'
import SiteLayout from './admin/eventos/abas/SiteLayout'
import SitePublico from './pages/SitePublico'
import CheckoutSeminario from './pages/CheckoutSeminario'
import MagicLink from './pages/MagicLink'
import Usuarios from './admin/eventos/Usuarios'
// Módulo financeiro central (spec 010)
import FinancasLayout from './admin/financas/FinancasLayout'
import FinancasDashboard from './admin/financas/Dashboard'
import Contas from './admin/financas/Contas'
import FinancasCadastros from './admin/financas/Cadastros'
import FinancasRelatorios from './admin/financas/Relatorios'

// Home do painel por papel: admin → módulo Eventos; tesouraria → Financeiro;
// portaria → Check-in direto
function PainelHome() {
  const { user } = useAuth()

  // Admin e financeiro entram no Dashboard
  if (user.roles.includes('admin') || user.roles.includes('treasury')) {
    return <Navigate to="/painel/dashboard" replace />
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
          <Route path="/site/:slug" element={<SitePublico />} />
          <Route path="/checkout/:slug" element={<CheckoutSeminario />} />
          <Route path="/acesso" element={<MagicLink />} />
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
            path="/minha-conta"
            element={
              <ProtectedRoute>
                <MinhaConta />
              </ProtectedRoute>
            }
          >
            <Route index element={<MeusDados />} />
            <Route path="pedidos" element={<MeusPedidos />} />
            <Route path="ingressos" element={<MeusIngressos />} />
            <Route path="suporte" element={<Suporte />} />
          </Route>
          <Route
            path="/painel"
            element={
              <RoleRoute roles={['admin', 'treasury', 'gate']}>
                <AdminLayout />
              </RoleRoute>
            }
          >
            <Route index element={<PainelHome />} />

            {/* ── Dashboard (visão geral) e Eventos (lista direta) ── */}
            <Route path="dashboard" element={<RoleRoute roles={['admin', 'treasury']}><PainelModulo /></RoleRoute>} />
            <Route path="eventos" element={<RoleRoute roles={['admin', 'treasury']}><ListaEventos /></RoleRoute>} />

            {/* ── Atendimento centralizado (sidebar, admin+financeiro) ── */}
            <Route path="atendimentos" element={<RoleRoute roles={['admin', 'treasury']}><Atendimentos /></RoleRoute>} />

            {/* ── Usuários da equipe (módulo, admin) ── */}
            <Route path="usuarios" element={<RoleRoute role="admin"><Usuarios /></RoleRoute>} />

            {/* ── Módulo Financeiro central (spec 010, admin+financeiro) ── */}
            <Route path="financas" element={<RoleRoute roles={['admin', 'treasury']}><FinancasLayout /></RoleRoute>}>
              <Route index element={<FinancasDashboard />} />
              <Route path="pagar" element={<Contas direction="payable" />} />
              <Route path="receber" element={<Contas direction="receivable" />} />
              <Route path="cadastros" element={<FinancasCadastros />} />
              <Route path="relatorios" element={<FinancasRelatorios />} />
            </Route>

            {/* ── Dentro de um evento (2ª camada de abas) ── */}
            <Route path="eventos/:eventId" element={<RoleRoute roles={['admin', 'treasury']}><EventoLayout /></RoleRoute>}>
              <Route index element={<PainelEvento />} />
              <Route path="inscritos" element={<Inscritos />} />
              <Route path="ingressos" element={<TiposLotes />} />
              <Route path="camisas" element={<Camisas />} />
              <Route path="cortesias" element={<Cortesias />} />
              <Route path="patrocinio" element={<Patrocinios />} />
              <Route path="orcamento" element={<Orcamento />} />
              <Route path="inscricoes" element={<Inscricoes />} />
              <Route path="site" element={<SiteLayout />} />
              <Route path="relatorios" element={<Relatorios />} />
              <Route path="checkin" element={<CheckinEvento />} />
              <Route path="trilha" element={<Auditoria />} />
            </Route>

            {/* ── Rotas focadas (tesouraria/portaria) — sem regressão ── */}
            <Route path="tesouraria" element={<Tesouraria />} />
            <Route path="financeiro" element={<RoleRoute roles={['treasury', 'admin']}><Financeiro /></RoleRoute>} />
            <Route path="checkin" element={<PortariaEventos />} />
            <Route path="checkin/:eventId" element={<Checkin />} />
            <Route path="landing/:eventId?" element={<RoleRoute role="admin"><Landing /></RoleRoute>} />
          </Route>
        </Routes>
        </CartProvider>
      </AuthProvider>
    </BrowserRouter>
  )
}
