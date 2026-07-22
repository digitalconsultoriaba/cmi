// Máscaras e campos reutilizáveis do checkout/inscrição (spec 015).

/** CPF (≤11 díg.) → 000.000.000-00 · CNPJ (>11) → 00.000.000/0000-00. */
export function maskCpfCnpj(value) {
  const d = (value || '').replace(/\D/g, '').slice(0, 14)
  if (d.length <= 11) {
    return d
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d{1,2})$/, '$1-$2')
  }
  return d
    .replace(/(\d{2})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1/$2')
    .replace(/(\d{4})(\d{1,2})$/, '$1-$2')
}

// Países com bandeira, DDI e máscara do número nacional (# = dígito).
// BR primeiro (padrão); os demais cobrem casos comuns. Sem máscara própria →
// só agrupa dígitos até 15.
export const COUNTRIES = [
  { code: 'BR', flag: '🇧🇷', dial: '55', mask: '(##) #####-####' },
  { code: 'PT', flag: '🇵🇹', dial: '351', mask: '### ### ###' },
  { code: 'US', flag: '🇺🇸', dial: '1', mask: '(###) ###-####' },
  { code: 'AR', flag: '🇦🇷', dial: '54', mask: '(##) ####-####' },
  { code: 'UY', flag: '🇺🇾', dial: '598', mask: '#### ####' },
  { code: 'PY', flag: '🇵🇾', dial: '595', mask: '(###) ######' },
  { code: 'CL', flag: '🇨🇱', dial: '56', mask: '# #### ####' },
  { code: 'ES', flag: '🇪🇸', dial: '34', mask: '### ### ###' },
  { code: 'IT', flag: '🇮🇹', dial: '39', mask: '### ### ####' },
  { code: 'FR', flag: '🇫🇷', dial: '33', mask: '# ## ## ## ##' },
  { code: 'GB', flag: '🇬🇧', dial: '44', mask: '#### ######' },
  { code: 'DE', flag: '🇩🇪', dial: '49', mask: '#### #######' },
]

export const countryByCode = (code) => COUNTRIES.find((c) => c.code === code) || COUNTRIES[0]

/** CEP → 00000-000. */
export function maskCep(value) {
  return (value || '').replace(/\D/g, '').slice(0, 8).replace(/(\d{5})(\d)/, '$1-$2')
}

/** Aplica a máscara (posicional por #) sobre os dígitos do valor. */
export function maskPhone(value, mask) {
  const digits = (value || '').replace(/\D/g, '')
  let out = ''
  let i = 0
  for (const ch of mask) {
    if (i >= digits.length) break
    if (ch === '#') { out += digits[i]; i++ } else { out += ch }
  }
  return out
}

/**
 * Telefone com seletor de país (bandeira + DDI) à esquerda e número mascarado.
 * Controlado: `country` (code) e `number` (nacional mascarado).
 */
export function PhoneField({ country, number, onCountry, onNumber }) {
  const c = countryByCode(country)
  return (
    <div className="ck-phone">
      <select className="ck-phone-country" value={c.code} onChange={(e) => onCountry(e.target.value)} aria-label="País (DDI)">
        {COUNTRIES.map((x) => <option key={x.code} value={x.code}>{x.flag} +{x.dial}</option>)}
      </select>
      <input
        className="ck-input ck-phone-input" inputMode="tel"
        value={number} onChange={(e) => onNumber(maskPhone(e.target.value, c.mask))}
        placeholder={c.mask.replace(/#/g, '0')}
      />
    </div>
  )
}
