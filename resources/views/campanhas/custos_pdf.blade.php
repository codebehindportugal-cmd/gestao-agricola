<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custos da produção - {{ $campanha->cultura?->nome }} {{ $campanha->ano }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #edf2f0;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.35;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            padding: 12mm;
        }

        header {
            border-bottom: 2px solid #059669;
            padding-bottom: 10px;
        }

        h1 {
            margin: 0;
            font-size: 22px;
        }

        h2 {
            margin: 18px 0 8px;
            color: #065f46;
            font-size: 14px;
            text-transform: uppercase;
        }

        .muted {
            color: #64748b;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 14px;
        }

        .card {
            border: 1px solid #d1fae5;
            border-radius: 8px;
            padding: 9px;
        }

        .label {
            color: #64748b;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .value {
            margin-top: 5px;
            font-size: 15px;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 6px;
            text-align: left;
            vertical-align: top;
            word-break: break-word;
        }

        th {
            background: #ecfdf5;
            color: #064e3b;
            font-size: 9px;
            text-transform: uppercase;
        }

        .right {
            text-align: right;
        }

        .totals td {
            background: #f8fafc;
            font-weight: 700;
        }

        .actions {
            position: fixed;
            right: 18px;
            bottom: 18px;
        }

        .actions button {
            border: 0;
            border-radius: 999px;
            background: #0f172a;
            color: #fff;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            padding: 10px 16px;
        }

        @media print {
            body {
                background: #fff;
            }

            .sheet {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
            }

            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    @php
        $money = fn ($value) => number_format((float) $value, 2, ',', ' ').' €';
        $number = fn ($value) => number_format((float) $value, 2, ',', ' ');
    @endphp

    <main class="sheet">
        <header>
            <h1>Custos da produção</h1>
            <p class="muted">
                {{ $campanha->cultura?->nome ?? 'Sem cultura' }} · Campanha {{ $campanha->ano }}
                · Gerado em {{ $dataGeracao }}
            </p>
        </header>

        <section class="summary">
            <div class="card">
                <div class="label">Custo total</div>
                <div class="value">{{ $money($resumo['custo_total']) }}</div>
            </div>
            <div class="card">
                <div class="label">Operações</div>
                <div class="value">{{ $money($resumo['total_operacoes']) }}</div>
            </div>
            <div class="card">
                <div class="label">Produtos</div>
                <div class="value">{{ $money($resumo['total_produtos']) }}</div>
            </div>
            <div class="card">
                <div class="label">Outros custos</div>
                <div class="value">{{ $money($resumo['total_custos_avulsos']) }}</div>
            </div>
            <div class="card">
                <div class="label">Produção real</div>
                <div class="value">{{ $number($resumo['producao_real']) }} kg</div>
            </div>
            <div class="card">
                <div class="label">Custo por kg</div>
                <div class="value">{{ $money($resumo['custo_por_kg']) }}</div>
            </div>
            <div class="card">
                <div class="label">Área</div>
                <div class="value">{{ $number($resumo['area_total']) }} ha</div>
            </div>
            <div class="card">
                <div class="label">Custo por ha</div>
                <div class="value">{{ $money($resumo['custo_por_ha']) }}</div>
            </div>
        </section>

        <h2>Custos por Operação</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 11%">Data</th>
                    <th style="width: 16%">Tipo</th>
                    <th style="width: 20%">Parcela</th>
                    <th style="width: 14%">Recurso</th>
                    <th class="right" style="width: 10%">Horas</th>
                    <th class="right" style="width: 10%">Comb.</th>
                    <th class="right" style="width: 10%">Produtos</th>
                    <th class="right" style="width: 9%">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($operacoes as $operacao)
                    <tr>
                        <td>{{ $operacao['data'] }}</td>
                        <td>{{ $operacao['tipo'] }}</td>
                        <td>{{ $operacao['parcela'] ?: 'N/A' }}</td>
                        <td>
                            {{ $operacao['maquina'] ?: $operacao['alfaia'] ?: $operacao['responsavel'] ?: $operacao['equipa'] ?: 'N/A' }}
                        </td>
                        <td class="right">{{ $number($operacao['duracao_horas']) }}</td>
                        <td class="right">{{ $number($operacao['combustivel_gasto_l']) }} L</td>
                        <td class="right">{{ $money($operacao['custo_produtos']) }}</td>
                        <td class="right">{{ $money($operacao['custo_total']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">Sem operações associadas a esta campanha.</td>
                    </tr>
                @endforelse
                <tr class="totals">
                    <td colspan="6">Total</td>
                    <td class="right">{{ $money($resumo['total_produtos']) }}</td>
                    <td class="right">{{ $money($resumo['total_operacoes'] + $resumo['total_produtos']) }}</td>
                </tr>
            </tbody>
        </table>

        <h2>Outros Custos da Campanha</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 14%">Data</th>
                    <th style="width: 18%">Tipo</th>
                    <th>Descrição</th>
                    <th class="right" style="width: 18%">Valor</th>
                </tr>
            </thead>
            <tbody>
                @forelse($custosAvulsos as $custo)
                    <tr>
                        <td>{{ $custo['data'] }}</td>
                        <td>{{ $custo['tipo'] }}</td>
                        <td>{{ $custo['descricao'] }}</td>
                        <td class="right">{{ $money($custo['valor']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Sem custos avulsos associados a esta campanha.</td>
                    </tr>
                @endforelse
                <tr class="totals">
                    <td colspan="3">Total</td>
                    <td class="right">{{ $money($resumo['total_custos_avulsos']) }}</td>
                </tr>
            </tbody>
        </table>
    </main>

    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>
</body>
</html>
