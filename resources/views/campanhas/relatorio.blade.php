<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modelo de registo de aplicação de Produtos Fitofarmacêuticos</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 7mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef2f7;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            line-height: 1.15;
        }

        .sheet {
            width: 297mm;
            min-height: 210mm;
            margin: 0 auto;
            background: #fff;
            padding: 10mm 7mm 7mm;
        }

        .top {
            display: grid;
            grid-template-columns: 1fr 3fr 1fr;
            align-items: start;
            min-height: 30mm;
        }

        .brand-left {
            display: flex;
            gap: 5px;
            align-items: flex-start;
            color: #4b5563;
            font-size: 6px;
            font-weight: 700;
            line-height: 1.05;
            text-transform: uppercase;
        }

        .flag {
            width: 10px;
            height: 16px;
            background: linear-gradient(90deg, #009246 0 45%, #fff 45% 52%, #ce2b37 52% 100%);
            border-radius: 1px;
            position: relative;
        }

        .flag::after {
            content: "";
            position: absolute;
            left: 3px;
            top: 6px;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: #f9c400;
        }

        .brand-right {
            color: #8b8f96;
            font-size: 8px;
            font-weight: 700;
            line-height: 1;
            text-align: right;
        }

        .brand-right strong {
            display: block;
            color: #969aa1;
            font-size: 28px;
            font-weight: 400;
            letter-spacing: -2px;
        }

        h1 {
            margin: 22mm 0 0;
            color: #1f4e86;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
        }

        .identification {
            margin-top: 10mm;
            font-size: 10px;
            font-weight: 700;
        }

        .line {
            display: flex;
            align-items: flex-end;
            gap: 4px;
            min-height: 15px;
        }

        .fill {
            flex: 1;
            min-height: 13px;
            border-bottom: 1px solid #111;
            padding: 0 4px 1px;
            font-weight: 400;
        }

        .short-fill {
            flex: 0 0 31%;
        }

        .grid-table {
            width: 100%;
            margin-top: 10mm;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .grid-table th,
        .grid-table td {
            border: 1px solid #333;
            padding: 3px 4px;
            text-align: center;
            vertical-align: middle;
            word-break: break-word;
        }

        .grid-table th {
            height: 34mm;
            background: #f1f1f1;
            font-size: 8px;
            font-weight: 700;
        }

        .grid-table td {
            height: 9.6mm;
            font-size: 8px;
        }

        .grid-table .left {
            text-align: left;
        }

        .w-parcela { width: 6.5%; }
        .w-cultura { width: 6%; }
        .w-area { width: 5.8%; }
        .w-inimigo { width: 11.2%; }
        .w-produto { width: 11.5%; }
        .w-av { width: 7.4%; }
        .w-est { width: 11.2%; }
        .w-auto { width: 10.6%; }
        .w-dose { width: 9.7%; }
        .w-volume { width: 8.2%; }
        .w-data { width: 8.9%; }

        .observations {
            margin-top: 2px;
            font-size: 7.4px;
            font-weight: 700;
            line-height: 1.22;
            text-align: justify;
        }

        .note {
            margin-top: 5px;
            border-top: 1px solid #111;
            border-bottom: 1px solid #111;
            padding: 3px 0;
            font-size: 8px;
            font-weight: 700;
        }

        .code {
            margin-top: 2px;
            color: #4b5563;
            font-size: 7px;
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
    <main class="sheet">
        <header class="top">
            <div class="brand-left">
                <span class="flag"></span>
                <span>
                    REPÚBLICA<br>
                    PORTUGUESA<br><br>
                    AGRICULTURA<br>
                    E ALIMENTAÇÃO
                </span>
            </div>

            <h1>Modelo de registo de aplicação de Produtos Fitofarmacêuticos</h1>

            <div class="brand-right">
                <strong>dgav</strong>
                Direção-Geral<br>
                de Alimentação<br>
                e Veterinária
            </div>
        </header>

        <section class="identification">
            <div class="line">
                <span>Identificação do Produtor</span>
                <span class="fill">{{ $identificacao['produtor'] }}</span>
            </div>
            <div class="line">
                <span>Nº de aplicador ou da entidade autorizada a aplicar quando não é o próprio produtor:</span>
                <span class="fill">{{ trim(($identificacao['aplicador'] ? $identificacao['aplicador'].' ' : '').($identificacao['aplicador_numero'] ? '('.$identificacao['aplicador_numero'].')' : '')) }}</span>
            </div>
            <div class="line">
                <span>Identificação da Exploração:</span>
                <span>Concelho</span>
                <span class="fill short-fill">{{ $identificacao['concelho'] }}</span>
                <span>Freguesia</span>
                <span class="fill">{{ $identificacao['freguesia'] }}</span>
            </div>
        </section>

        <table class="grid-table">
            <thead>
                <tr>
                    <th rowspan="2" class="w-parcela">Parcela</th>
                    <th rowspan="2" class="w-cultura">Cultura</th>
                    <th rowspan="2" class="w-area">Área<br>tratada<br>(m²/ha)</th>
                    <th rowspan="2" class="w-inimigo">Inimigo ou efeito<br>a atingir<br>(praga, doença,<br>infestantes (1) ou<br>outros)</th>
                    <th rowspan="2" class="w-produto">Produto<br>Fitofarmacêutico<br><br>(2)</th>
                    <th rowspan="2" class="w-av">N.º AV,<br>APV, ACP<br>ou AEE (3)</th>
                    <th colspan="2">Estabelecimento de venda onde foi<br>adquirido o produto fitofarmacêutico</th>
                    <th rowspan="2" class="w-dose">Concentração/<br>Dose<br><br>(L ou kg p.c./hl)<br><br>(L ou kg p.c./ha)<br>por aplicação (4)</th>
                    <th rowspan="2" class="w-volume">Volume de<br>calda<br>aplicada<br>(l/ha)</th>
                    <th rowspan="2" class="w-data">Data(s) da(s)<br>Aplicação(ões)<br><br>(dd/mm/aaaa)<br><br>(5)</th>
                </tr>
                <tr>
                    <th class="w-est">Nome do<br>estabelecimento</th>
                    <th class="w-auto">Número de<br>autorização de<br>exercício da<br>atividade</th>
                </tr>
            </thead>
            <tbody>
                @foreach($registosFitofarmaceuticos as $registo)
                    <tr>
                        <td class="left">{{ $registo['parcela'] }}</td>
                        <td>{{ $registo['cultura'] }}</td>
                        <td>{{ $registo['area_tratada'] }}</td>
                        <td class="left">{{ $registo['inimigo_efeito'] }}</td>
                        <td class="left">{{ $registo['produto'] }}</td>
                        <td>{{ $registo['numero_autorizacao'] }}</td>
                        <td class="left">{{ $registo['estabelecimento_nome'] }}</td>
                        <td>{{ $registo['estabelecimento_autorizacao'] }}</td>
                        <td>{{ $registo['dose'] }}</td>
                        <td>{{ $registo['volume_calda'] }}</td>
                        <td>{{ $registo['data_aplicacao'] }}</td>
                    </tr>
                @endforeach

                @if($registosFitofarmaceuticos->isEmpty())
                    <tr>
                        <td colspan="11" class="left">Sem aplicações fitofarmacêuticas registadas nesta campanha.</td>
                    </tr>
                @endif

                @for($i = 0; $i < $linhasVazias; $i++)
                    <tr>
                        <td>&nbsp;</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <p class="observations">
            Observações: (1) – Na identificação das infestantes, quando não é possível identificar a espécie, indicar se é monocotiledónea (folha estreita) ou dicotiledónea (folha larga), se é anual, vivaz ou perene e se é herbácea ou lenhosa; (2) – Nome comercial do produto fitofarmacêutico; (3) – Nº de autorização de venda, autorização provisória de venda, autorização de comércio paralelo ou autorização excecional de emergência; (4) – pretende-se que esteja registada a concentração usada de produto fitofarmacêutico (l produto comercial/hl) ou a dose usada, expressa em kg produto comercial/ha, tendo em conta que a concentração/dose recomendada no rótulo também se refere ao produto comercial; (5) – pretende-se que o produtor indique todas as datas em que aplicou o produto.
        </p>

        <p class="note">
            Nota: o preenchimento de um Caderno de Campo não obriga ao registo individualizado da informação constante do presente modelo
        </p>
        <p class="code">Mod.08.01/DSMDS/2023</p>
    </main>

    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>
</body>
</html>
