<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
<style>
  body{font-family:DejaVu Sans, sans-serif;font-size:11px;color:#222}
  h1{font-size:16px;margin:0 0 4px}
  h2{font-size:13px;margin:14px 0 6px;color:#1e3a8a}
  table{width:100%;border-collapse:collapse;margin-bottom:8px}
  th,td{border:1px solid #ccc;padding:4px 6px;text-align:left}
  th{background:#1e3a8a;color:#fff}
  .r{text-align:right}
  .muted{color:#666}
</style></head><body>
  <h1>Orçamento — {{ $event->name }}</h1>
  <div class="muted">Previsão financeira do evento</div>

  <h2>Resumo</h2>
  <table>
    <tr><td>Custo total previsto</td><td class="r">R$ {{ $summary['totalCost'] }}</td></tr>
    <tr><td>Receita prevista (ingressos)</td><td class="r">R$ {{ $summary['ticketRevenue'] }}</td></tr>
    <tr><td>Receita prevista (patrocínios)</td><td class="r">R$ {{ $summary['sponsorshipExpected'] }}</td></tr>
    <tr><td>Receita total prevista</td><td class="r">R$ {{ $summary['totalRevenue'] }}</td></tr>
    <tr><td><strong>Resultado previsto</strong></td><td class="r"><strong>R$ {{ $summary['result'] }}</strong></td></tr>
    <tr><td>Investimento próprio necessário</td><td class="r">R$ {{ $summary['ownInvestment'] }}</td></tr>
    <tr><td>Ponto de equilíbrio (pagantes)</td><td class="r">{{ $summary['breakEvenPaying'] ?? '—' }}</td></tr>
  </table>

  <h2>Itens de custo previstos</h2>
  <table>
    <thead><tr><th>Descrição</th><th>Categoria</th><th class="r">Total</th><th>Status</th></tr></thead>
    <tbody>
      @foreach($plan->costItems as $i)
        <tr><td>{{ $i->description }}</td><td>{{ $i->category }}</td><td class="r">R$ {{ $i->total_amount }}</td><td>{{ $i->status }}</td></tr>
      @endforeach
    </tbody>
  </table>

  <h2>Lotes de ingresso previstos</h2>
  <table>
    <thead><tr><th>Lote</th><th class="r">Valor</th><th class="r">Qtd prevista</th><th class="r">Receita prevista</th></tr></thead>
    <tbody>
      @foreach($plan->ticketLots as $l)
        <tr><td>{{ $l->name }}</td><td class="r">R$ {{ $l->unit_price }}</td><td class="r">{{ $l->expected_quantity }}</td><td class="r">R$ {{ $l->expectedRevenue() }}</td></tr>
      @endforeach
    </tbody>
  </table>

  <h2>Patrocínios previstos</h2>
  <table>
    <thead><tr><th>Cota</th><th class="r">Valor</th><th class="r">Qtd</th><th>Status</th><th class="r">Receita prevista</th></tr></thead>
    <tbody>
      @foreach($plan->sponsorships as $s)
        <tr><td>{{ $s->name }}</td><td class="r">R$ {{ $s->unit_value }}</td><td class="r">{{ $s->quantity }}</td><td>{{ $s->status }}</td><td class="r">R$ {{ $s->expectedRevenue() }}</td></tr>
      @endforeach
    </tbody>
  </table>

  <h2>Orçado × Realizado</h2>
  <table>
    <thead><tr><th>Indicador</th><th class="r">Orçado</th><th class="r">Realizado</th></tr></thead>
    <tbody>
      <tr><td>Despesa</td><td class="r">R$ {{ $comparison['cost']['budgeted'] }}</td><td class="r">R$ {{ $comparison['cost']['actual'] }}</td></tr>
      <tr><td>Receita</td><td class="r">R$ {{ $comparison['revenue']['budgeted'] }}</td><td class="r">R$ {{ $comparison['revenue']['actual'] }}</td></tr>
      <tr><td>Patrocínio</td><td class="r">R$ {{ $comparison['sponsorship']['budgeted'] }}</td><td class="r">R$ {{ $comparison['sponsorship']['actual'] }}</td></tr>
      <tr><td>Ingressos</td><td class="r">{{ $comparison['tickets']['budgeted'] }}</td><td class="r">{{ $comparison['tickets']['actual'] }}</td></tr>
      <tr><td>Resultado</td><td class="r">R$ {{ $comparison['result']['budgeted'] }}</td><td class="r">R$ {{ $comparison['result']['actual'] }}</td></tr>
    </tbody>
  </table>
</body></html>
