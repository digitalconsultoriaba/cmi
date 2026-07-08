// Máscara de WhatsApp com DDI 55 fixo: "55 (27) 99267-9890".
// Aceita valor já mascarado ou só dígitos; idempotente.
export function maskWhatsapp(value) {
  let d = String(value ?? '').replace(/\D/g, '')
  if (!d) return ''
  // Remove o DDI 55 quando já presente (número completo com 12+ dígitos).
  if (d.startsWith('55') && d.length > 11) d = d.slice(2)
  d = d.slice(0, 11) // DDD (2) + número (até 9)

  const ddd = d.slice(0, 2)
  const p1 = d.slice(2, 7)
  const p2 = d.slice(7, 11)

  let out = '55'
  if (ddd) out += ` (${ddd}${ddd.length === 2 ? ')' : ''}`
  if (p1) out += ` ${p1}`
  if (p2) out += `-${p2}`
  return out
}
