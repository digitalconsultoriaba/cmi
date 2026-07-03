import axios from 'axios'

// Cliente HTTP da plataforma: cookies de sessão (Sanctum SPA) e unwrap do
// envelope { data } definido em specs/001-fundacao/contracts/api-conventions.md.
export const http = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

// Antes da primeira mutação, o Sanctum exige o cookie CSRF.
let csrfReady = false

export async function csrf() {
  if (!csrfReady) {
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
    csrfReady = true
  }
}

// 401 → sessão caiu: quem provê o QueryClient registra este callback para
// invalidar a query ['auth','me'] (ver AuthProvider).
let onUnauthenticated = null

export function setOnUnauthenticated(callback) {
  onUnauthenticated = callback
}

http.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && onUnauthenticated) {
      onUnauthenticated()
    }
    return Promise.reject(error)
  },
)

export async function apiGet(url, config) {
  const response = await http.get(url, config)
  return response.data.data
}

export async function apiPost(url, body, config) {
  await csrf()
  const response = await http.post(url, body, config)
  return response.data.data
}
