# API de Ingestao (v1)

API autenticada para ingestao de custos, aplicacoes de produtos, colheitas, lotes e receitas. Os endpoints usam JSON e ficam sob `/api/v1`.

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
