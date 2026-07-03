import { useQuery } from '@tanstack/react-query'
import { apiGet } from './lib/api'

export default function App() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['health'],
    queryFn: () => apiGet('/health'),
  })

  return (
    <main style={{ fontFamily: 'sans-serif', padding: '2rem' }}>
      <h1>Plataforma de Eventos</h1>
      <p>Fundação (spec 001). As áreas do produto chegam nas próximas specs.</p>
      {isLoading && <p>Verificando API…</p>}
      {isError && <p>API indisponível — rode <code>make dev</code>.</p>}
      {data && <p>API: {data.status}</p>}
    </main>
  )
}
