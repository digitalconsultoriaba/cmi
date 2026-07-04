# Referências visuais — 009-refatoracao-telas

14 capturas do protótipo aprovado pelo organizador (2026-07-04). São o
**contrato visual** desta spec: estrutura de navegação, hierarquia dos blocos,
componentes e identidade. Ordem cronológica dos arquivos:

| # | Arquivo (hora) | Tela |
|---|---|---|
| 1 | 02.08.30 | **Módulo → aba Painel**: filtro evento+período; 8 cards (eventos, publicados, próximos, inscritos ativos, receita confirmada/prevista, patrocínio pago, reembolsos abertos); rosca "Eventos por situação"; curva "Inscrições por mês" |
| 2 | 02.08.43 | **Sidebar azul** com logo GLMEES + menu do sistema anfitrião; módulo com abas Painel/Eventos/Eventos das lojas/Atendimentos/Tipos; aba **Eventos** = listagem |
| 3 | 02.09.11 | **Evento → aba Painel**: cabeçalho (Voltar, nome, badge, Editar/Banner/Cancelar); abas do evento; 8 contadores; rosca "Situação dos ingressos"; barras "Inscrições por Loja" |
| 4 | 02.09.18 | **Evento → Inscritos**: busca + filtros (loja, situação, período); tabela participante/loja/ingresso/camisa/valor/situação/compra/pagamento; badge "casal" |
| 5 | 02.09.26 | **Evento → Ingressos**: "Tipos de ingresso" + Novo tipo; tabela nome/preço/capac./assentos/camisa/kit com editar/excluir |
| 6 | 02.09.35 | **Evento → Camisas**: modelos com **estoque por tamanho** (total/vendidas/disponível), add tamanho inline, Relatório por modelo e geral |
| 7 | 02.09.42 | **Evento → Cortesias**: filtro + Gerar vouchers; tabela código/situação/distribuído por/utilizado por + Copiar |
| 8 | 02.09.49 | **Evento → Patrocínio**: cards por patrocinador com parcelas (valor/vencimento/situação/recebimento) + Novo patrocínio |
| 9 | 02.09.55 | **Modal Novo patrocínio**: empresa, contato, valor, parcelas, forma, vencimentos ("1ª + 30 em 30" \| Personalizado), 1º vencimento, observações |
| 10 | 02.10.06 | **Evento → Relatórios**: seletor de relatório + filtros (loja, ano/mês ou de/até, busca) + **preview em tabela** + Exportar .xlsx |
| 11 | 02.10.16 | **Evento → Check-in**: "Validação de entrada" (código + Ler QR + Validar) ao lado de rosca "Presença"; 4 cards (comprados/presentes/ausentes/% presença); busca + lista com **Registrar presença** manual por linha |
| 12 | 02.10.25 | **Modal Editar evento** (parte 1): nome, tipo, descrição, data/hora, local, capacidade, limite, público, janela de vendas, contato |
| 13 | 02.10.30 | **Modal Editar evento** (parte 2): modo de preço, **regras do evento** (toggles), gratuidade X→Y com limites, observações internas |
| 14 | 02.12.01 | **Lado do inscrito**: sidebar azul; "Eventos e Ingressos" com abas Catálogo/Meus eventos/Minhas compras; **cards de evento** (badge, título, loja/tipo, data, local, preço) |

## Decisões de escopo (organizador, 2026-07-04)

- **Sidebar**: azul com a logo CMI/GLMEES (`public/logo.png`) como identidade;
  só "Eventos e Ingressos" navega — os demais itens do menu anfitrião são
  casca visual (esta plataforma é standalone).
- **Multi-loja FORA**: "Eventos das lojas", coluna/filtro "Loja", "Inscrições
  por Loja" e ranking por loja não entram (não há modelo de lojas aqui). Onde
  a referência mostrava recorte por loja, usar recorte por **tipo de ingresso**.
- **Implementar de fato**: navegação em 2 camadas + identidade; painéis com
  gráficos (módulo e evento); check-in com presença manual; camisas com
  estoque na tela; relatórios com preview. Demais abas reorganizam telas que
  já existem.
