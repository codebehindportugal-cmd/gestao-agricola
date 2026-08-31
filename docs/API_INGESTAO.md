# API de Ingestao (v1)

API autenticada para ingestao de custos, trabalho/mao de obra, aplicacoes de produtos, colheitas, lotes e receitas. Os endpoints usam JSON e ficam sob `/api/v1`.

**Todos os endpoints `/api/v1` exigem token Sanctum**, incluindo as leituras de cadastro (terrenos, parcelas, culturas, operacoes, maquinas, alfaias). As leituras precisam so de token valido; as escritas exigem tambem role `admin`, `gestor_agricola` ou `operador`, e os endpoints de ingestao exigem ainda a ability correspondente.

## Autenticacao

Emitir token para um utilizador existente com role `admin`, `gestor_agricola` ou `operador`:

```bash
php artisan agri:emitir-token andre@example.com --nome=integracao --abilities=custos:write --abilities=aplicacoes:write --abilities=colheitas:write --abilities=receitas:write
```

Enviar o token em todos os pedidos:

```http
Authorization: Bearer <token>
Content-Type: application/json
```

## Envelope

Todas as respostas novas usam:

```json
{
  "sucesso": true,
  "dados": {},
  "avisos": [],
  "erros": []
}
```

Erros de validacao devolvem `422` com `sucesso: false`.

## Idempotencia

Use `referencia_externa` para evitar duplicados. Se a referencia ja existir, a API devolve o registo existente, adiciona um aviso e nao cria novo registo.

## Leitura de cadastro

Todas exigem apenas um token valido (sem ability especifica):

| Rota | Devolve | Filtros |
| --- | --- | --- |
| `GET /api/v1/terrenos` `/parcelas` `/culturas` `/operacoes` `/maquinas` `/alfaias` | paginado, 15 por pagina | `?page=` |
| `GET /api/v1/campanhas` | lista completa com `nome` (`<cultura> <ano>`) | `?ano=` `?status=` |
| `GET /api/v1/funcionarios` | inclui `valor_hora` | `?status=` `?q=` |
| `GET /api/v1/equipas` | inclui os funcionarios de cada equipa | - |
| `GET /api/v1/produtos` | inclui `numero_autorizacao_dgav` e `conforme_dgav` | `?tipo=` `?q=` |

Sao estes os endpoints a usar para resolver nomes -> ids antes de escrever.

## POST /api/v1/custos

Ability exigida: `custos:write`.

Aceita um custo unico ou lote:

```json
{
  "custos": [
    {
      "descricao": "Gasoleo agricola - tanque cheio",
      "tipo": "energia",
      "valor": 148.50,
      "data": "2026-07-15",
      "campanha": "Milho 2026",
      "maquina": "Trator John Deere 6120",
      "referencia_externa": "fatura-2026-0842"
    },
    {
      "descricao": "Jornada de mao de obra - poda",
      "tipo": "mao_obra",
      "valor": 90.00,
      "data": "2026-07-15",
      "campanha": "Milho 2026",
      "funcionario": "Antonio Silva"
    }
  ]
}
```

Resposta:

```json
{
  "sucesso": true,
  "dados": {
    "criados": [12, 13],
    "ignorados": [],
    "custos": [
      {
        "id": 12,
        "descricao": "Gasoleo agricola - tanque cheio",
        "tipo": "energia",
        "valor": "148.50",
        "data": "2026-07-15",
        "referencia_externa": "fatura-2026-0842"
      }
    ]
  },
  "avisos": [],
  "erros": []
}
```

Exemplo curl:

```bash
curl -X POST http://localhost/api/v1/custos \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d "{\"descricao\":\"Gasoleo agricola\",\"tipo\":\"energia\",\"valor\":148.50,\"data\":\"2026-07-15\",\"referencia_externa\":\"fatura-2026-0842\"}"
```

## POST /api/v1/aplicacoes

Ability exigida: `aplicacoes:write`.

Cria uma `Operacao` e as linhas `OperacaoProduto` associadas. Produtos `fitofarmaceutico` precisam de `numero_autorizacao_dgav`.

O `tipo` por omissao e `tratamento fitossanitário` - o valor que o caderno de campo e o dashboard filtram. So mude o tipo se souber o que esta a fazer.

Campos alem dos do exemplo abaixo:

- **Meios utilizados** (por id ou nome): `maquina`, `alfaia`, `funcionario`, `equipa`; e `duracao_horas`, `distancia_km`, `combustivel_gasto_l`, `data_fim`.
- **Varias parcelas numa chamada**: em vez de `parcela`, envie `parcelas` como lista. Cada entrada aceita `parcela`, `cultura`, `area_tratada`, `volume_calda`, `duracao_horas`, `combustivel_gasto_l`, `observacoes` e `produtos` proprios (caindo para os `produtos` do topo quando omitidos). E criada **uma operacao por parcela**; com `referencia_externa` definida, cada uma fica com o sufixo `-1`, `-2`, ...
- **Distribuicao**: `duracao_horas`, `distancia_km`, `combustivel_gasto_l`, `custo_estimado` e `custo_real` indicados no topo sao valores **totais** e ficam repartidos pelas parcelas - proporcionalmente a `area_tratada` quando todas a tiverem, senao em partes iguais. Um valor indicado dentro da parcela ganha ao do topo. A resposta traz um aviso a dizer qual criterio foi usado.
- **Quantidade derivada da dose**: se omitir `quantidade` mas indicar `dose` e `dose_unidade`, a quantidade e calculada - `L/ha` x `area_tratada`, `L/hl` x (`volume_calda`/100), `L/1000L` x (`volume_calda`/1000). Sem `dose_unidade` reconhecida, indique a `quantidade`.
- **`custo_unitario`** cai para o `custo_unitario` do produto quando omitido.

A resposta traz `dados.operacoes` (todas) e `dados.operacao` (a primeira, por compatibilidade).

Exemplo com varias parcelas e meios:

```json
{
  "campanha": "Milho 2026",
  "data": "2026-08-20",
  "maquina": "Landini",
  "alfaia": "Pulverizador 800L",
  "duracao_horas": 16,
  "combustivel_gasto_l": 40,
  "referencia_externa": "pulv-2026-08-20",
  "produtos": [
    { "produto": "Montana", "dose": 2, "dose_unidade": "L/ha", "custo_unitario": 10 }
  ],
  "parcelas": [
    { "parcela": "Infantes", "area_tratada": 0.28 },
    { "parcela": "Cumeira 2", "area_tratada": 1.14 }
  ]
}
```

Payload:

```json
{
  "campanha": "Milho 2026",
  "parcela": "Parcela Norte",
  "data": "2026-07-16",
  "tipo": "tratamento",
  "produtor_nome": "Andre",
  "aplicador_nome": "Andre",
  "aplicador_numero_autorizacao": "PT-12345",
  "exploracao_concelho": "Santarem",
  "exploracao_freguesia": "Alcanhoes",
  "referencia_externa": "aplic-2026-0116",
  "produtos": [
    {
      "produto": "3456",
      "quantidade": 5,
      "dose": 2.5,
      "dose_unidade": "L/ha",
      "area_tratada": 2.0,
      "volume_calda": 400,
      "finalidade": "Controlo de infestantes",
      "intervalo_seguranca_dias": 30,
      "custo_unitario": 18.90
    }
  ]
}
```

Resposta:

```json
{
  "sucesso": true,
  "dados": {
    "operacao": {
      "id": 44,
      "tipo": "tratamento",
      "data": "2026-07-16",
      "referencia_externa": "aplic-2026-0116",
      "produtos": [
        {
          "id": 7,
          "nome": "Produto exemplo",
          "numero_autorizacao_dgav": "3456",
          "quantidade": "5.00",
          "dose": "2.500",
          "dose_unidade": "L/ha",
          "area_tratada": "2.00",
          "volume_calda": "400.00",
          "finalidade": "Controlo de infestantes",
          "intervalo_seguranca_dias": 30,
          "custo_unitario": "18.90",
          "custo_total": "94.50"
        }
      ]
    }
  },
  "avisos": [],
  "erros": []
}
```

Exemplo curl:

```bash
curl -X POST http://localhost/api/v1/aplicacoes \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d "{\"campanha\":\"Milho 2026\",\"parcela\":\"Parcela Norte\",\"data\":\"2026-07-16\",\"tipo\":\"tratamento\",\"referencia_externa\":\"aplic-2026-0116\",\"produtos\":[{\"produto\":\"3456\",\"quantidade\":5,\"dose\":2.5,\"dose_unidade\":\"L/ha\",\"area_tratada\":2,\"volume_calda\":400,\"finalidade\":\"Controlo de infestantes\",\"intervalo_seguranca_dias\":30,\"custo_unitario\":18.90}]}"
```

## POST /api/v1/trabalhos

Ability exigida: `trabalhos:write` ou `custos:write`.

Regista trabalho/mao de obra. Cria **uma `Operacao`**, **uma `Jornada` por funcionario e por dia trabalhado** (o detalhe para o caderno de campo) e **um `Custo` agregado de tipo `mao_obra`** - e o custo que a tesouraria contabiliza; as jornadas sao so detalhe, nao ha dupla contagem.

Campos:

- `tarefa` (obrigatorio), `tipo` (default `colheita`), `observacoes`
- `campanha`, `parcela`, `cultura`, `maquina`, `alfaia` - opcionais, por id ou nome
- Periodo: `data_inicio` (obrigatorio) + **um de** `data_fim`, `dias` ou `semanas`
- `incluir_fins_de_semana` (default `false` - sabados e domingos sao saltados)
- `horas_por_dia` (obrigatorio)
- Pessoas: **um de** `funcionarios` (lista de ids/nomes), `equipa` (traz os funcionarios associados) ou `numero_pessoas`
- Custo: `valor_hora` (fallback para quem nao tem `valor_hora` na ficha) ou `custo_total` (sobrepoe todo o calculo)
- `referencia_externa` (idempotencia)

Regras:

- Com `funcionarios` ou `equipa`, o custo e a soma de `horas_por_dia x dias x valor_hora` de cada pessoa. Quem nao tiver `valor_hora` na ficha nem `valor_hora` no payload entra a zero e gera aviso.
- Com apenas `numero_pessoas` nao ha funcionarios para associar: nao sao criadas jornadas, so a operacao e o custo agregado (`numero_pessoas x dias x horas x valor_hora`), com aviso.
- Se o custo der zero, nao e criado registo de custo - so a operacao, com aviso.
- Limite de 2000 jornadas por pedido.

Payload:

```json
{
  "tarefa": "Apanha da fruta",
  "tipo": "colheita",
  "campanha": "Milho 2026",
  "data_inicio": "2026-08-14",
  "semanas": 3,
  "incluir_fins_de_semana": true,
  "horas_por_dia": 8,
  "numero_pessoas": 18,
  "valor_hora": 5,
  "referencia_externa": "apanha-2026-08-14"
}
```

Resposta:

```json
{
  "sucesso": true,
  "dados": {
    "operacao": { "id": 88, "tipo": "colheita", "data": "2026-08-14" },
    "dias_trabalhados": 21,
    "jornadas": 0,
    "custo": { "id": 14, "tipo": "mao_obra", "valor": "15120.00", "data": "2026-08-14" }
  },
  "avisos": ["nenhum funcionario registado indicado; nao foram criadas jornadas, so a operacao e o custo agregado."],
  "erros": []
}
```

## POST /api/v1/faturas

Ability exigida: `faturas:write` ou `custos:write`.

Regista uma fatura de compra. Num so pedido cria a `Despesa` e as suas `FaturaItem`, resolve ou cria os `Produto`, da entrada em stock (`MovimentoStock` + `Stock`, pelo mesmo servico que o ecra de despesas usa) e cria o `Custo` correspondente - que e o que a tesouraria conta como saida.

Campos:

- `data` (obrigatorio), `numero_fatura`, `fornecedor`, `titulo` (derivado se omitido), `notas`
- `categoria`: `combustivel`, `sementes`, `fertilizantes`, `fitofarmaceuticos`, `equipamento`, `mao_obra`, `outro` (default `outro`)
- `campanha` (por id ou nome)
- `valor`: total da fatura. Se omitido, e calculado das linhas com IVA; se indicado e divergir mais de 2 centimos, prevalece o indicado e a resposta traz aviso
- `linhas[]`: `descricao` (obrigatorio), `quantidade` (obrigatorio, > 0), `preco_unitario` (obrigatorio), `iva_percentagem` (0, 6, 13 ou 23), `produto` (id / nº DGAV / nome), `tipo_produto`, `numero_autorizacao_dgav`, `unidade_medida`, `notas`
- Interruptores, todos `true` por omissao: `criar_produtos`, `actualizar_custo_unitario`, `dar_entrada_em_stock`, `criar_custo`

Regras:

- Produto que nao exista e criado com o nome, tipo, unidade e o preco da fatura. Um `fitofarmaceutico` novo sem `numero_autorizacao_dgav` da 422.
- Se a referencia for ambigua (varios produtos possiveis), devolve 422 com os candidatos em vez de criar um duplicado.
- Linha sem produto identificado fica na fatura mas sem ligacao ao catalogo nem stock, com aviso.
- Idempotencia por `numero_fatura` (+ `fornecedor` quando indicado): repetir devolve a despesa existente sem duplicar.
- Mapeamento categoria -> tipo de custo: `combustivel`->`energia`, `sementes`/`fertilizantes`/`fitofarmaceuticos`->`material`, `equipamento`->`maquinaria`, `mao_obra`->`mao_obra`, `outro`->`outro`.

Payload:

```json
{
  "numero_fatura": "FT 2026/123",
  "fornecedor": "Agro Silva Lda",
  "data": "2026-08-20",
  "categoria": "fitofarmaceuticos",
  "linhas": [
    {
      "produto": "DGAV-77",
      "descricao": "Montana 5L",
      "quantidade": 4,
      "preco_unitario": 45,
      "iva_percentagem": 6
    }
  ]
}
```

A resposta traz `dados.despesa` (com as linhas), `dados.movimentos_stock` e `dados.custo`.

## Calendario: compromissos

`GET /api/v1/compromissos` (so token) e `POST /api/v1/compromissos` (ability `compromissos:write` ou `custos:write`).

Um compromisso e uma tarefa, pagamento, manutencao ou prazo legal com data. Categorias: `pagamento`, `tarefa_agricola`, `manutencao`, `prazo_legal`.

**Leitura** - filtros `de`, `ate`, `categoria`, `estado`, `atrasados=1`.

**Escrita** - aceita um compromisso ou um lote em `compromissos: [...]`. Campos: `titulo` e `categoria` e `data` (obrigatorios), `descricao`, `tipo` (etiqueta livre: IMI, IUC, Seguro, Poda), `entidade`, `hora`, `valor`, `antecedencia_aviso_dias`, `notas`, `referencia_externa` (idempotencia), e as ligacoes `campanha`, `parcela`, `cultura`, `maquina`, `funcionario` por id ou nome.

**Recorrencia** - `recorrencia`: `nenhuma`, `mensal`, `trimestral`, `semestral`, `anual` ou `personalizada` (com `recorrencia_intervalo` + `recorrencia_unidade`: dia/semana/mes/ano), e `recorrencia_fim` opcional. A linha criada e ela propria a primeira ocorrencia; as seguintes sao materializadas ate 18 meses a frente e ficam ligadas por `compromisso_pai_id`. Gerar e idempotente. Somas mensais usam `addMonthsNoOverflow`, portanto 31/01 + 1 mes da 28/02 e nao 03/03.

`POST /api/v1/compromissos/{id}/concluir` marca como feito. Com valor, cria o `Custo` correspondente (portanto entra na tesouraria), liga-o ao compromisso e garante que a proxima ocorrencia da serie existe. Concluir duas vezes nao duplica o custo. Campos: `valor_pago`, `data_conclusao`, `criar_custo`.

Mapeamento categoria -> tipo de custo: `pagamento`/`prazo_legal` -> `outro`, `tarefa_agricola` -> `mao_obra`, `manutencao` -> `manutencao`.

O comando `php artisan agri:gerar-compromissos --meses=18` materializa as ocorrencias de todas as series; convem agenda-lo (ex.: mensal).

Payload:

```json
{
  "compromissos": [
    {
      "titulo": "IMI - 1ª prestação",
      "categoria": "pagamento",
      "tipo": "IMI",
      "entidade": "Autoridade Tributária",
      "data": "2027-05-31",
      "valor": 340,
      "recorrencia": "anual",
      "antecedencia_aviso_dias": 15,
      "referencia_externa": "imi-2027-1"
    }
  ]
}
```

## POST /api/v1/colheitas

Ability exigida: `colheitas:write`.

Cria uma `Colheita` e um ou mais `Lote`. Cada lote guarda a origem (`terreno_id`) e `data_colheita` para rastreabilidade.

Payload:

```json
{
  "campanha": "Milho 2026",
  "cultura": "Milho",
  "data": "2026-10-18",
  "quantidade_total": 12500,
  "referencia_externa": "colheita-2026-10-18-milho",
  "lotes": [
    {
      "codigo": "LOTE-2026-A",
      "terreno": "Terreno Norte",
      "data_colheita": "2026-10-18",
      "quantidade": 7000,
      "unidade": "kg"
    },
    {
      "terreno": "Terreno Sul",
      "quantidade": 5500,
      "unidade": "kg"
    }
  ]
}
```

Resposta:

```json
{
  "sucesso": true,
  "dados": {
    "colheita": {
      "id": 21,
      "data": "2026-10-18",
      "quantidade_total": "12500.00",
      "referencia_externa": "colheita-2026-10-18-milho",
      "lotes": [
        {
          "id": 31,
          "codigo": "LOTE-2026-A",
          "data_colheita": "2026-10-18",
          "quantidade": "7000.00",
          "unidade": "kg"
        }
      ]
    }
  },
  "avisos": [],
  "erros": []
}
```

Exemplo curl:

```bash
curl -X POST http://localhost/api/v1/colheitas \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d "{\"campanha\":\"Milho 2026\",\"cultura\":\"Milho\",\"data\":\"2026-10-18\",\"quantidade_total\":12500,\"referencia_externa\":\"colheita-2026-10-18-milho\",\"lotes\":[{\"codigo\":\"LOTE-2026-A\",\"terreno\":\"Terreno Norte\",\"data_colheita\":\"2026-10-18\",\"quantidade\":7000,\"unidade\":\"kg\"},{\"terreno\":\"Terreno Sul\",\"quantidade\":5500,\"unidade\":\"kg\"}]}"
```

## POST /api/v1/receitas

Ability exigida: `receitas:write`.

Aceita uma receita unica ou lote. Tipos aceites: `venda_colheita`, `subsidio`, `servico`, `outro`.

Payload:

```json
{
  "receitas": [
    {
      "descricao": "Venda de milho - lote colheita",
      "tipo": "venda_colheita",
      "valor": 8450.00,
      "data": "2026-10-20",
      "campanha": "Milho 2026",
      "lote": "LOTE-2026-A",
      "comprador_nome": "Cooperativa Agricola",
      "documento": "FT 2026/338",
      "referencia_externa": "venda-2026-10-20-milho"
    },
    {
      "descricao": "Subsidio PAC - pagamento unico",
      "tipo": "subsidio",
      "valor": 3200.00,
      "data": "2026-12-05",
      "campanha": "Milho 2026",
      "referencia_externa": "subsidio-pac-2026"
    }
  ]
}
```

Resposta:

```json
{
  "sucesso": true,
  "dados": {
    "criados": [51, 52],
    "ignorados": [],
    "receitas": [
      {
        "id": 51,
        "descricao": "Venda de milho - lote colheita",
        "tipo": "venda_colheita",
        "valor": "8450.00",
        "data": "2026-10-20",
        "referencia_externa": "venda-2026-10-20-milho"
      }
    ]
  },
  "avisos": [],
  "erros": []
}
```

Exemplo curl:

```bash
curl -X POST http://localhost/api/v1/receitas \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d "{\"descricao\":\"Subsidio PAC - pagamento unico\",\"tipo\":\"subsidio\",\"valor\":3200,\"data\":\"2026-12-05\",\"campanha\":\"Milho 2026\",\"referencia_externa\":\"subsidio-pac-2026\"}"
```

## GET /api/v1/tesouraria

Exige token Sanctum. Query params opcionais:

- `campanha`: id ou nome, exemplo `Milho 2026`
- `de`: data inicial
- `ate`: data final

Resposta:

```json
{
  "sucesso": true,
  "dados": {
    "entradas": 11650.00,
    "saidas": 238.50,
    "saldo": 11411.50,
    "por_tipo_entrada": {
      "venda_colheita": 8450.00,
      "subsidio": 3200.00
    },
    "por_tipo_saida": {
      "energia": 148.50,
      "mao_obra": 90.00
    }
  },
  "avisos": [],
  "erros": []
}
```

Exemplo curl:

```bash
curl -X GET "http://localhost/api/v1/tesouraria?campanha=Milho%202026&de=2026-01-01&ate=2026-12-31" \
  -H "Authorization: Bearer <token>"
```
