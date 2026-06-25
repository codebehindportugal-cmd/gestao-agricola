<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Despesas — {{ $nomeMes }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin:0; background:#f8fafc; color:#0f172a; font-family:Arial,Helvetica,sans-serif; font-size:11px; line-height:1.4; }
        .sheet { width:210mm; min-height:297mm; margin:0 auto; background:#fff; padding:12mm; }
        header { border-bottom:2px solid #059669; padding-bottom:10px; margin-bottom:16px; }
        h1 { margin:0; font-size:20px; }
        h2 { margin:18px 0 8px; color:#065f46; font-size:11px; text-transform:uppercase; letter-spacing:.06em; }
        .muted { color:#64748b; }
        .kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-bottom:18px; }
        .kpi { border:1px solid #e2e8f0; border-radius:6px; padding:9px; background:#f8fafc; }
        .kpi-label { font-size:8px; text-transform:uppercase; color:#64748b; letter-spacing:.08em; }
        .kpi-value { font-size:15px; font-weight:700; margin-top:2px; }
        .kpi-sub { font-size:8px; color:#94a3b8; margin-top:1px; }
        .analytics-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:18px; }
        .ana-card { border:1px solid #e2e8f0; border-radius:6px; padding:10px; }
        .ana-card ul { margin:6px 0 0; padding:0; list-style:none; }
        .ana-card li { display:flex; justify-content:space-between; font-size:9px; padding:2px 0; border-bottom:1px solid #f1f5f9; }
        .ana-card li:last-child { border:0; }
        /* faturas */
        .fatura { margin-bottom:14px; break-inside:avoid; }
        .fatura-header { background:#ecfdf5; border:1px solid #d1fae5; border-radius:5px 5px 0 0; padding:7px 10px; display:flex; justify-content:space-between; align-items:flex-start; }
        .fatura-title { font-weight:700; font-size:11px; }
        .fatura-meta { font-size:9px; color:#64748b; margin-top:1px; }
        .fatura-total { text-align:right; }
        .fatura-total .value { font-size:13px; font-weight:700; color:#059669; }
        .fatura-total .sub { font-size:8px; color:#94a3b8; }
        table { width:100%; border-collapse:collapse; font-size:10px; }
        thead th { background:#065f46; color:#fff; padding:5px 8px; text-align:left; font-size:9px; text-transform:uppercase; letter-spacing:.05em; }
        tbody td { padding:4px 8px; border-bottom:1px solid #e2e8f0; }
        tbody tr:nth-child(even) { background:#f8fafc; }
        .text-right { text-align:right; }
        .text-center { text-align:center; }
        .total-row td { font-weight:700; background:#ecfdf5; border-top:2px solid #059669; }
        .badge { display:inline-block; padding:1px 5px; border-radius:999px; font-size:8px; font-weight:600; background:#d1fae5; color:#065f46; text-transform:capitalize; }
        .no-items td { font-style:italic; color:#94a3b8; font-size:9px; }
        footer { margin-top:20px; font-size:8px; color:#94a3b8; text-align:right; border-top:1px solid #e2e8f0; padding-top:6px; }
        @media print { body{background:#fff;} .sheet{padding:0;} }
    </style>
</head>
<body>
<div class="sheet">
    <header>
        <h1>Resumo de Despesas</h1>
        <div class="muted">{{ $nomeMes }} &mdash; Horta da Maria / Gestão Agrícola</div>
    </header>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi">
            <div class="kpi-label">Total do mês</div>
            <div class="kpi-value">{{ number_format($resumo['total'], 2, ',', '.') }} €</div>
            <div class="kpi-sub">{{ $resumo['count'] }} fatura(s)</div>
        </div>
        @if($analytics['tem_items'])
        <div class="kpi">
            <div class="kpi-label">Subtotal s/ IVA</div>
            <div class="kpi-value">{{ number_format($analytics['subtotal'], 2, ',', '.') }} €</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">IVA total</div>
            <div class="kpi-value" style="color:#dc2626">{{ number_format($analytics['iva_total'], 2, ',', '.') }} €</div>
            <div class="kpi-sub">para declaração de IVA</div>
        </div>
        @endif
        @foreach(array_slice($resumo['por_categoria'], 0, $analytics['tem_items'] ? 1 : 3, true) as $cat => $val)
            @if($val > 0)
            <div class="kpi">
                <div class="kpi-label">{{ ucfirst(str_replace('_', ' ', $cat)) }}</div>
                <div class="kpi-value">{{ number_format($val, 2, ',', '.') }} €</div>
                <div class="kpi-sub">{{ $resumo['total'] > 0 ? number_format($val / $resumo['total'] * 100, 0) : 0 }}% do total</div>
            </div>
            @endif
        @endforeach
    </div>

    @if($analytics['tem_items'])
    <!-- Análise -->
    <h2>Análise do mês</h2>
    <div class="analytics-grid">
        <div class="ana-card">
            <strong style="font-size:10px">Por fornecedor</strong>
            <ul>
                @foreach($analytics['por_fornecedor'] as $f)
                <li>
                    <span>{{ $f['fornecedor'] }}</span>
                    <span style="font-weight:600">{{ number_format($f['total'], 2, ',', '.') }} €</span>
                </li>
                @endforeach
            </ul>
        </div>
        <div class="ana-card">
            <strong style="font-size:10px">Produtos mais comprados</strong>
            <ul>
                @foreach($analytics['top_descricoes'] as $p)
                <li>
                    <span style="max-width:70%;overflow:hidden">{{ $p['descricao'] }}</span>
                    <span style="font-weight:600">×{{ $p['count'] }} &mdash; {{ number_format($p['total'], 2, ',', '.') }} €</span>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <!-- Por categoria -->
    <h2>Por categoria</h2>
    <table style="margin-bottom:18px">
        <thead><tr>
            <th>Categoria</th>
            <th class="text-right">Total (€)</th>
            <th class="text-right">% do total</th>
        </tr></thead>
        <tbody>
            @foreach($resumo['por_categoria'] as $cat => $val)
            @if($val > 0)
            <tr>
                <td><span class="badge">{{ str_replace('_', ' ', $cat) }}</span></td>
                <td class="text-right">{{ number_format($val, 2, ',', '.') }}</td>
                <td class="text-right">{{ $resumo['total'] > 0 ? number_format($val / $resumo['total'] * 100, 1) : 0 }}%</td>
            </tr>
            @endif
            @endforeach
            <tr class="total-row">
                <td>Total</td>
                <td class="text-right">{{ number_format($resumo['total'], 2, ',', '.') }}</td>
                <td class="text-right">100%</td>
            </tr>
        </tbody>
    </table>

    <!-- Detalhe faturas -->
    <h2>Detalhe das faturas</h2>
    @foreach($despesas as $d)
    <div class="fatura">
        <div class="fatura-header">
            <div>
                <div class="fatura-title">{{ $d['titulo'] }}</div>
                <div class="fatura-meta">
                    {{ $d['data'] }}
                    @if($d['fornecedor'] !== '-') &mdash; {{ $d['fornecedor'] }} @endif
                    @if($d['numero_fatura'] !== '-') &mdash; # {{ $d['numero_fatura'] }} @endif
                    &mdash; <span class="badge">{{ str_replace('_', ' ', $d['categoria']) }}</span>
                </div>
            </div>
            <div class="fatura-total">
                <div class="value">{{ number_format($d['valor'], 2, ',', '.') }} €</div>
                @if(!empty($d['items']))
                <div class="sub">s/ IVA {{ number_format($d['subtotal'], 2, ',', '.') }} € | IVA {{ number_format($d['iva'], 2, ',', '.') }} €</div>
                @endif
            </div>
        </div>
        @if(!empty($d['items']))
        <table>
            <thead><tr>
                <th>Descrição</th>
                <th class="text-right" style="width:60px">Qtd</th>
                <th class="text-right" style="width:75px">Preço unit.</th>
                <th class="text-center" style="width:40px">IVA%</th>
                <th class="text-right" style="width:70px">Total</th>
            </tr></thead>
            <tbody>
                @foreach($d['items'] as $item)
                <tr>
                    <td>{{ $item['descricao'] }}</td>
                    <td class="text-right">{{ number_format($item['quantidade'], 3, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($item['preco_unitario'], 2, ',', '.') }} €</td>
                    <td class="text-center">{{ number_format($item['iva_percentagem'], 0) }}%</td>
                    <td class="text-right">{{ number_format($item['total_com_iva'], 2, ',', '.') }} €</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3">Total</td>
                    <td class="text-center">—</td>
                    <td class="text-right">{{ number_format($d['valor'], 2, ',', '.') }} €</td>
                </tr>
            </tbody>
        </table>
        @else
        <table><tbody>
            <tr class="no-items"><td colspan="5" style="padding:6px 10px">Sem linhas — total registado manualmente</td></tr>
        </tbody></table>
        @endif
    </div>
    @endforeach

    <footer>
        Gerado em {{ now()->format('d/m/Y H:i') }} &mdash; Horta da Maria / Gestão Agrícola
    </footer>
</div>
<script>window.onload = function(){ window.print(); };</script>
</body>
</html>
