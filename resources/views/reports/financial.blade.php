<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
<style>
  body{font-family:DejaVu Sans, sans-serif;font-size:11px;color:#222}
  h1{font-size:16px;margin:0 0 10px}
  table{width:100%;border-collapse:collapse}
  th,td{border:1px solid #ccc;padding:4px 6px;text-align:left}
  th{background:#1e3a8a;color:#fff}
</style></head><body>
  <h1>Relatório financeiro — {{ $type }}</h1>
  <table>
    <thead><tr>@foreach($columns as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
    <tbody>
      @foreach($rows as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach
    </tbody>
  </table>
</body></html>
