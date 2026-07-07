import SecaoSimples from './SecaoSimples'

/** Cabeçalho editável (rótulos da seção) + lista de itens dinâmicos. */
export default function SecaoDinamica({ eventId, section, languages, reload, labelFields, children }) {
  return (
    <div>
      {labelFields && (
        <SecaoSimples eventId={eventId} section={section} languages={languages} reload={reload}>
          {labelFields}
        </SecaoSimples>
      )}
      <hr />
      {children}
    </div>
  )
}
