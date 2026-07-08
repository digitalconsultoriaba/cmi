// Máscara de WhatsApp com DDI 55 fixo: "55 (27) 99267-9890".
// O DDI 55 é sempre tratado como prefixo (removido antes de parsear), para que
// apagar chegue a vazio e o DDD não seja confundido com o DDI. Idempotente.
export function maskWhatsapp(value) {
  let d = String(value ?? '').replace(/\D/g, '')
  if (d.startsWith('55')) d = d.slice(2) // remove o DDI 55 (prefixo fixo)
  d = d.slice(0, 11) // DDD (2) + número (até 9)

  if (!d) return '' // vazio → mostra o placeholder e permite apagar tudo

  let out = `55 (${d.slice(0, 2)}`
  if (d.length >= 3) out += `) ${d.slice(2, 7)}`
  if (d.length >= 8) out += `-${d.slice(7, 11)}`
  return out
}
