import axios from 'axios'

// Cliente HTTP da plataforma: cookies de sessão (Sanctum SPA) e unwrap do
// envelope { data } definido em specs/001-fundacao/contracts/api-conventions.md.
export const http = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: { Accept: 'application/json' },
})

export async function apiGet(url, config) {
  const response = await http.get(url, config)
  return response.data.data
}

export async function apiPost(url, body, config) {
  const response = await http.post(url, body, config)
  return response.data.data
}
