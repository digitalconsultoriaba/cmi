// Dinheiro: telas em pt-BR aceitam vírgula; a API usa string decimal com ponto
// e 2 casas (contrato da spec 001).

/** "1.234,56" | "1234.56" | "1234" → "1234.56" (ou null se inválido/vazio) */
export function parseMoney(input) {
  if (input === null || input === undefined || String(input).trim() === '') {
    return null
  }

  let value = String(input).trim().replace(/[R$\s]/g, '')

  if (value.includes(',')) {
    value = value.replace(/\./g, '').replace(',', '.')
  }

  const number = Number(value)
  if (Number.isNaN(number) || number < 0) {
    return null
  }

  return number.toFixed(2)
}

/** "1234.56" → "R$ 1.234,56" */
export function formatMoney(decimalString) {
  if (decimalString === null || decimalString === undefined) return '—'

  return Number(decimalString).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  })
}
