import { NavLink, Outlet } from 'react-router-dom'

const TABS = [
  { to: '/painel/modulo', label: 'Painel', end: true },
  { to: '/painel/modulo/eventos', label: 'Eventos' },
  { to: '/painel/modulo/atendimentos', label: 'Atendimentos' },
  { to: '/painel/modulo/tipos', label: 'Tipos' },
]

/** Módulo "Eventos e Ingressos" — primeira camada de abas (spec 009). */
export default function ModuloLayout() {
  return (
    <>
      <div className="page-header d-print-none">
        <div className="page-pretitle">Eventos</div>
        <h2 className="page-title">Eventos e Ingressos</h2>
      </div>

      <ul className="nav nav-tabs mb-3">
        {TABS.map((tab) => (
          <li className="nav-item" key={tab.to}>
            <NavLink className="nav-link" to={tab.to} end={tab.end}>{tab.label}</NavLink>
          </li>
        ))}
      </ul>

      <Outlet />
    </>
  )
}
