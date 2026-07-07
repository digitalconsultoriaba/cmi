# Contract — Admin: Upload de mídia do Site

Mesmo grupo admin/prefixo `events/{event}`. Reaproveita o disco `public` e o padrão de upload do `EventController::banner()`.

## `POST /admin/events/{event}/site/media`

Upload de uma imagem (multipart/form-data). Guarda em `storage/app/public/site/` e retorna o path relativo + URL pública.

**Body** (multipart): `file` (image).

Validação (`UploadSiteMediaRequest`):
- `file`: obrigatório, `image`, mimes `jpeg,png,webp,svg`, máx. ~4096 KB.

- **201** →
```json
{ "data": { "path": "site/a1b2c3.png", "url": "http://localhost/storage/site/a1b2c3.png" } }
```
O front guarda `path` no campo do payload (ex.: `speaker.photo`, `identity.logoPath`, `gallery[]`) e usa `url` para preview.

- **422** → arquivo ausente, tipo não permitido ou acima do limite.
- **403** → papel fora de admin/treasury.

## Observações

- Substituição de mídia: quando um campo de path é trocado, o front envia o novo `path`; a limpeza do arquivo antigo segue o padrão de cleanup existente (best-effort) no service ao detectar troca.
- Nenhum PAN/CVV/segredo transita aqui; apenas imagens públicas do site.
