// Retenção do carrinho do checkout no navegador (spec 015).
// localStorage (cookie é pequeno demais) por evento; limpa ao concluir a compra.

const keyFor = (slug) => `cmi-checkout:${slug}`

export function loadCheckout(slug) {
  try {
    const raw = localStorage.getItem(keyFor(slug))
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

export function saveCheckout(slug, data) {
  try {
    localStorage.setItem(keyFor(slug), JSON.stringify(data))
  } catch {
    /* cota/privacidade: apenas não persiste */
  }
}

export function clearCheckout(slug) {
  try {
    localStorage.removeItem(keyFor(slug))
  } catch {
    /* ignore */
  }
}
