import { NavLink, Outlet } from 'react-router-dom'

const TABS = [
  { to: '/painel/financas', label: 'Painel', end: true },
  { to: '/painel/financas/pagar', label: 'Contas a Pagar' },
  { to: '/painel/financas/receber', label: 'Contas a Receber' },
  { to: '/painel/financas/cadastros', label: 'Cadastros' },
  { to: '/painel/financas/relatorios', label: 'Relatórios' },
]

export default function FinancasLayout() {
  return (
    <>
      <div className="page-header d-print-none">
        <div className="page-pretitle">Financeiro</div>
        <h2 className="page-title">Contas a Pagar e Receber</h2>
      </div>
      <ul className="nav nav-tabs mb-3">
        {TABS.map((t) => (
          <li className="nav-item" key={t.to}>
            <NavLink className="nav-link" to={t.to} end={t.end}>{t.label}</NavLink>
          </li>
        ))}
      </ul>
      <Outlet />
    </>
  )
}
