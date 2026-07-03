// Extrai erros de validação (422) e mensagens gerais da shape padrão de erro
// ({ message, type, status, errors? } — contrato da spec 001).
export function parseApiError(error) {
  const response = error?.response

  if (!response) {
    return { message: 'Falha de conexão. Tente novamente.', fields: {} }
  }

  const { message, errors, type } = response.data ?? {}

  return {
    message: message ?? 'Algo deu errado. Tente novamente.',
    type: type ?? 'unknown',
    status: response.status,
    fields: errors ?? {},
  }
}

export function fieldError(parsed, field) {
  return parsed?.fields?.[field]?.[0] ?? null
}
