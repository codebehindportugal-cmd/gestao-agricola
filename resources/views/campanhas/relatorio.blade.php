<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório da Campanha - {{ $campanha->ano }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            color: #333;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 12px;
            color: #666;
        }
        @media print {
            body {
                margin: 0;
                font-size: 12px;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório da Campanha Agrícola</h1>
        <p><strong>Exploração:</strong> {{ $exploracao }}</p>
        <p><strong>Campanha:</strong> {{ $campanha->ano }}</p>
        <p><strong>Cultura:</strong> {{ $cultura }}</p>
        <p><strong>Período:</strong> {{ $periodo }}</p>
    </div>

    <div class="section">
        <h2>Resumo de Produção</h2>
        <table>
            <tr>
                <td><strong>Quantidade Colhida:</strong></td>
                <td>{{ number_format($producao['quantidade_colhida'], 2) }} kg</td>
            </tr>
            <tr>
                <td><strong>Perdas:</strong></td>
                <td>{{ number_format($producao['perdas'], 2) }} kg</td>
            </tr>
            <tr>
                <td><strong>Qualidade:</strong></td>
                <td>{{ $producao['qualidade'] }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Resumo Financeiro</h2>
        <table>
            <tr>
                <td><strong>Custo Total:</strong></td>
                <td>€ {{ number_format($financeiro['custo_total'], 2) }}</td>
            </tr>
            <tr>
                <td><strong>Custo por kg:</strong></td>
                <td>€ {{ number_format($financeiro['custo_por_kg'], 2) }}</td>
            </tr>
            <tr>
                <td><strong>Custo por ha:</strong></td>
                <td>€ {{ number_format($financeiro['custo_por_ha'], 2) }}</td>
            </tr>
        </table>

        <h3>Custos por Categoria</h3>
        <table>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th>Valor (€)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($financeiro['custos_por_categoria'] as $categoria => $valor)
                <tr>
                    <td>{{ ucfirst($categoria) }}</td>
                    <td>{{ number_format($valor, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Lista de Operações Realizadas</h2>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Custo (€)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($operacoes as $operacao)
                <tr>
                    <td>{{ $operacao['data'] }}</td>
                    <td>{{ $operacao['tipo'] }}</td>
                    <td>{{ number_format($operacao['custo'], 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3">Nenhuma operação encontrada.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Produtos Fitofarmacêuticos Aplicados</h2>
        <table>
            <thead>
                <tr>
                    <th>Nome do Produto</th>
                    <th>Nº Autorização DGAV</th>
                    <th>Dose</th>
                    <th>Área Tratada (ha)</th>
                    <th>Data de Aplicação</th>
                </tr>
            </thead>
            <tbody>
                @forelse($produtosFitofarmaceuticos as $produto)
                <tr>
                    <td>{{ $produto['nome'] }}</td>
                    <td>{{ $produto['numero_autorizacao'] }}</td>
                    <td>{{ $produto['dose'] }}</td>
                    <td>{{ number_format($produto['area_tratada'], 2) }}</td>
                    <td>{{ $produto['data_aplicacao'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">Nenhum produto fitofarmacêutico aplicado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>Relatório gerado em {{ $dataGeracao }}</p>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()">Imprimir Relatório</button>
    </div>
</body>
</html>