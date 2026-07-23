import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../../lib/api'
import Loading from '../../components/Loading'

const money = (value) => Number(value ?? 0).toLocaleString('pt-BR', {
  style: 'currency', currency: 'BRL',
})

const STATUS_BADGE = {
  paid: 'bg-green-lt', confirmed: 'bg-green-lt', courtesy: 'bg-purple-lt',
  used: 'bg-blue-lt', reserved: 'bg-yellow-lt', awaiting_payment: 'bg-yellow-lt',
  cancelled: 'bg-red-lt', transferred: 'bg-secondary-lt', refunded: 'bg-red-lt',
}

const pct = (part, total) => (total > 0 ? Math.min(100, Math.round((part / total) * 100)) : 0)

/** Card de métrica no padrão do template (subheader + h1 + progresso). */
function StatCard({ subheader, value, hint, progress, progressColor = 'bg-primary' }) {
  return (
    <div className="col-sm-6 col-lg-3">
      <div className="card">
        <div className="card-body">
          <div className="subheader">{subheader}</div>
          <div className="d-flex align-items-baseline">
            <div className="h1 mb-0 me-2">{value}</div>
          </div>
          {hint && <div className="text-secondary mt-2">{hint}</div>}
          {progress !== undefined && (
            <div className="progress progress-sm mt-2">
              <div className={`progress-bar ${progressColor}`} style={{ width: `${progress}%` }}
                role="progressbar" aria-valuenow={progress} aria-valuemin="0" aria-valuemax="100" />
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

export default function Dashboard() {
  const { data, dataUpdatedAt, refetch, isFetching } = useQuery({
    queryKey: ['admin', 'dashboard'],
    queryFn: () => apiGet('/admin/dashboard'),
  })

  if (!data) return <Loading fullscreen={false} />

  const { people, revenue, shirts, byLot, byMethod, courtesies, ticketsByStatus } = data

  return (
    <>
      <div className="page-header d-print-none">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">Visão geral</div>
            <h1 className="page-title">{data.event.name}</h1>
          </div>
          <div className="col-auto ms-auto d-print-none">
            <div className="btn-list">
              <a href="/api/admin/reports/attendees.xlsx" className="btn">
                Inscritos .xlsx
              </a>
              <button className="btn btn-primary" disabled={isFetching} onClick={() => refetch()}>
                Atualizar
              </button>
            </div>
          </div>
        </div>
        <div className="text-secondary mt-1">
          Números derivados dos registros em {new Date(dataUpdatedAt).toLocaleTimeString('pt-BR')}.
        </div>
      </div>

      <div className="row row-deck row-cards mb-3">
        <StatCard subheader="Pessoas confirmadas"
          value={people.confirmed}
          hint={`de ${people.capacity ?? '∞'} da capacidade`}
          progress={pct(people.confirmed, people.capacity)}
          progressColor="bg-primary" />
        <StatCard subheader="Presenças"
          value={`${people.present}`}
          hint={`${people.absent} ainda não chegaram`}
          progress={pct(people.present, people.confirmed)}
          progressColor="bg-green" />
        <StatCard subheader="Receita confirmada"
          value={money(revenue.confirmed)}
          hint={Number(revenue.refunded) > 0
            ? `${money(revenue.refunded)} devolvidos`
            : 'nada devolvido'} />
        <StatCard subheader="Receita prevista"
          value={money(revenue.projected)}
          hint={`${money(revenue.pending)} em pedidos abertos`} />
      </div>

      <div className="row row-deck row-cards mb-3">
        <div className="col-lg-6">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Camisas para produção (por pessoa)</h3>
            </div>
            <div className="card-table table-responsive">
              <table className="table table-vcenter">
                <thead>
                  <tr><th>Modelo</th><th>Tamanho</th><th className="text-end">Qtde</th></tr>
                </thead>
                <tbody>
                  {shirts.grid.map((cell, i) => (
                    <tr key={i}>
                      <td>{cell.model ?? <span className="text-secondary">não informado</span>}</td>
                      <td>{cell.size ?? <span className="text-secondary">—</span>}</td>
                      <td className="text-end">{cell.count}</td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr>
                    <th colSpan={2}>Total (= pessoas confirmadas)</th>
                    <th className="text-end">{shirts.totalPeople}</th>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <div className="col-lg-6">
          <div className="card mb-3">
            <div className="card-header"><h3 className="card-title">Vendas por lote</h3></div>
            <div className="card-table table-responsive">
              <table className="table table-vcenter">
                <thead>
                  <tr><th>Lote</th><th>Ocupação</th><th className="text-end">Receita</th></tr>
                </thead>
                <tbody>
                  {byLot.map((lot) => (
                    <tr key={lot.lot}>
                      <td>{lot.lot}</td>
                      <td style={{ minWidth: '10rem' }}>
                        <div className="d-flex align-items-center gap-2">
                          <span className="text-secondary small text-nowrap">
                            {lot.sold}{lot.limit ? ` / ${lot.limit}` : ''}
                          </span>
                          {lot.limit && (
                            <div className="progress progress-sm flex-fill">
                              <div className="progress-bar" style={{ width: `${pct(lot.sold, lot.limit)}%` }}
                                role="progressbar" />
                            </div>
                          )}
                        </div>
                      </td>
                      <td className="text-end">{money(lot.revenue)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div className="card">
            <div className="card-header"><h3 className="card-title">Recebimentos por forma</h3></div>
            <div className="card-table table-responsive">
              <table className="table table-vcenter">
                <thead>
                  <tr><th>Forma</th><th className="text-end">Qtde</th><th className="text-end">Valor</th></tr>
                </thead>
                <tbody>
                  {byMethod.map((row) => (
                    <tr key={row.method}>
                      <td className="text-capitalize">{row.method}</td>
                      <td className="text-end">{row.count}</td>
                      <td className="text-end">{money(row.amount)}</td>
                    </tr>
                  ))}
                  {byMethod.length === 0 && (
                    <tr><td colSpan={3} className="text-secondary">Nenhum recebimento ainda.</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div className="row row-deck row-cards">
        <div className="col-lg-6">
          <div className="card">
            <div className="card-header"><h3 className="card-title">Ingressos por situação</h3></div>
            <div className="card-body">
              <div className="d-flex flex-wrap gap-2">
                {ticketsByStatus.map((row) => (
                  <span key={row.status} className={`badge ${STATUS_BADGE[row.status] ?? 'bg-secondary-lt'}`}>
                    {row.label}: {row.count}
                  </span>
                ))}
                {ticketsByStatus.length === 0 && <span className="text-secondary">Sem ingressos.</span>}
              </div>
            </div>
          </div>
        </div>
        <div className="col-lg-6">
          <div className="card">
            <div className="card-header"><h3 className="card-title">Cortesias</h3></div>
            <div className="card-body">
              <div className="row g-3">
                {[
                  ['Vouchers emitidos', courtesies.issued],
                  ['Resgatados', courtesies.redeemed],
                  ['Ingressos cortesia ativos', courtesies.courtesyTickets],
                ].map(([label, value]) => (
                  <div className="col-auto" key={label}>
                    <div className="subheader">{label}</div>
                    <div className="h3 mb-0">{value}</div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}
