# API de Ingestao (v1)

API autenticada para ingestao de custos e aplicacoes de produtos. Os endpoints usam JSON e ficam sob `/api/v1`.

## Autenticacao

Emitir token para um utilizador existente com role `admin`, `gestor_agricola` ou `operador`:

```bash
php artisan agri:emitir-token andre@example.com --nome=integracao --abilities=custos:write --abilities=aplicacoes:write
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
