// Máscara de moeda brasileira: digita-se em centavos e formata como 1.234,56.
// O valor emitido (string mascarada) é aceito por parseMoney na hora de salvar.
function maskBRL(v) {
  const digits = String(v ?? '').replace(/\D/g, '')
  if (!digits) return ''
  return (parseInt(digits, 10) / 100).toLocaleString('pt-BR', {
    minimumFractionDigits: 2, maximumFractionDigits: 2,
  })
}

/**
 * Campo de dinheiro com prefixo R$ e máscara pt-BR. `value` pode vir como
 * decimal da API ("250.00") ou já mascarado ("250,00"); `onChange` recebe a
 * string mascarada.
 */
export default function MoneyInput({ value, onChange, className = 'form-control', placeholder = '0,00', disabled, autoFocus, sm }) {
  return (
    <div className={`input-group${sm ? ' input-group-sm' : ''}`}>
      <span className="input-group-text">R$</span>
      <input
        className={className}
        inputMode="numeric"
        placeholder={placeholder}
        value={maskBRL(value)}
        disabled={disabled}
        autoFocus={autoFocus}
        onChange={(e) => onChange(maskBRL(e.target.value))}
      />
    </div>
  )
}
