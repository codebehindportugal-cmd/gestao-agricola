<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Despesas - {{ $nomeMes }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f8fafc;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.4;
        }
        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            padding: 12mm;
        }
        header { border-bottom: 2px solid #059669; padding-bottom: 10px; margin-bottom: 16px; }
        h1 { margin: 0; font-size: 20px; color: #0f172a; }
        h2 { margin: 16px 0 8px; color: #065f46; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
        .muted { color: #64748b; }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px;
            background: #f8fafc;
        }
        .card-label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.08em; }
        .card-value { font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 2px; }
        .card-sub { font-size: 9px; color: #64748b; margin-top: 1px; }
        .cat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
            margin-bottom: 20px;
        }
        .cat-card {
            border: 1px solid #d1fae5;
            border-radius: 6px;
            padding: 8px;
            background: #f0fdf4;
        }
        .cat-label { font-size: 9px; color: #065f46; text-transform: capitalize; }
        .cat-val { font-size: 13px; font-weight: 700; color: #059669; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        thead th {
            background: #065f46;
            color: #fff;
            padding: 6px 8px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
        .text-right { text-align: right; }
        .total-row td { font-weight: 700; background: #ecfdf5; border-top: 2px solid #059669; }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 600;
            text-transform: capitalize;
            background: #d1fae5;
            color: #065f46;
        }
        footer { margin-top: 20px; font-size: 9px; color: #94a3b8; text-align: right; }
        @media print {
            body { background: #fff; }
            .sheet { padding: 0; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <header>
        <h1>Resumo de Despesas</h1>
        <div class="muted">{{ $nomeMes }} &mdash; Gestão Agrícola</div>
    </header>

    <div class="summary-grid">
        <div class="card">
            <div class="card-label">Total do mês</div>
            <div class="card-value">{{ number_format($resumo['total'], 2, ',', '.') }} €</div>
            <div class="card-sub">{{ $resumo['count'] }} despesa(s)</div>
        </div>
        @foreach(array_slice($resumo['por_categoria'], 0, 3, true) as $cat => $val)
            @if($val > 0)
            <div class="card">
                <div class="card-label">{{ ucfirst(str_replace('_', ' ', $cat)) }}</div>
                <div class="card-value">{{ number_format($val, 2, ',', '.') }} €</div>
                <div class="card-sub">{{ $resumo['total'] > 0 ? number_format($val / $resumo['total'] * 100, 0) : 0 }}% do total</div>
            </div>
            @endif
        @endforeach
    </div>

    <h2>Por categoria</h2>
    <div class="cat-grid">
        @foreach($resumo['por_categoria'] as $cat => $val)
            <div class="cat-card">
                <div class="cat-label">{{ str_replace('_', ' ', $cat) }}</div>
                <div class="cat-val">{{ number_format($val, 2, ',', '.') }} €</div>
            </div>
        @endforeach
    </div>

    <h2>Detalhe das despesas</h2>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Título</th>
                <th>Fornecedor</th>
                <th>Nº Fatura</th>
                <th>Categoria</th>
                <th class="text-right">Valor</th>
            </tr>
        </thead>
        <tbody>
            @foreach($despesas as $d)
            <tr>
                <td>{{ $d['data'] }}</td>
                <td>{{ $d['titulo'] }}</td>
                <td class="muted">{{ $d['fornecedor'] }}</td>
                <td class="muted">{{ $d['numero_fatura'] }}</td>
                <td><span class="badge">{{ str_replace('_', ' ', $d['categoria']) }}</span></td>
                <td class="text-right">{{ number_format($d['valor'], 2, ',', '.') }} €</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="5">Total</td>
                <td class="text-right">{{ number_format($resumo['total'], 2, ',', '.') }} €</td>
            </tr>
        </tbody>
    </table>

    <footer>
        Gerado em {{ now()->format('d/m/Y H:i') }} &mdash; Horta da Maria / Gestão Agrícola
    </footer>
</div>

<script>
    window.onload = function () { window.print(); };
</script>
</body>
</html>
