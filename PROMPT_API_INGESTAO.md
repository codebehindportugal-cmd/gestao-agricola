# BLOCO 5 — API de Ingestão de Custos e Aplicações

> Objetivo: uma API autenticada para onde um cliente externo (app, script, ou o
> extrator de imagem por IA do Bloco 2) envia **custos** e **aplicações de produtos
> fitofarmacêuticos** já estruturados, e o sistema **insere-os** — alimentando o
> caderno de campo (DGAV) e a agregação de custos automaticamente.

Correr os prompts por ordem (5.1 → 5.2 → 5.3 → 5.4). Cada um assume o anterior feito.

---

## Prompt 5.1 — Base da API, autenticação e resolução de referências

```
Lê o CLAUDE.md. Vamos criar uma API de ingestão versionada, consistente com o padrão
JSON já existente (OperacaoController, TerrenoController, ParcelaController).

1. Autenticação
   - Verifica se laravel/sanctum está instalado (composer.json). Se não, instala e publica.
   - A API usa tokens pessoais (personal access tokens) com abilities:
     'custos:write' e 'aplicacoes:write'.
   - Cria um comando artisan `agri:emitir-token {email} {--nome=} {--abilities=*}`
     que gera um token para um utilizador existente e imprime o token uma vez.
   - O utilizador do token tem de ter role admin, gestor_agricola ou operador para escrever.

2. Estrutura
   - Rotas em routes/api.php sob o prefixo `/api/v1`, middleware `auth:sanctum` + throttle.
   - Controllers em App\Http\Controllers\Api\V1\.
   - Todas as respostas usam um envelope JSON consistente:
     { "sucesso": bool, "dados": {...}|null, "avisos": [ ... ], "erros": [ ... ] }
   - Cria um trait ou base controller `RespondeJson` com métodos ok(), criado(), erro422().

3. Resolução de referências (importante — o cliente envia por nome, não por ID)
   Cria um serviço App\Services\ResolvedorReferencias com métodos que aceitam ID OU nome:
   - resolverCampanha(id|nome, ?ano)   → Campanha ou erro (se ambíguo, devolve candidatos)
   - resolverParcela(id|nome|codigo)   → Parcela
   - resolverProduto(id|numero_autorizacao_dgav|nome, ?criarSeInexistente, ?tipo) → Produto
   - resolverMaquina(id|nome), resolverFuncionario(id|nome)
   Quando não encontra, devolve erro claro com o valor recebido. Quando há mais de um
   resultado, devolve 422 com a lista de candidatos para o cliente desambiguar.

Não implementes ainda os endpoints de custos/aplicações — só a base, auth e o resolvedor.
Escreve um teste que confirme que um pedido sem token devolve 401 e com token válido passa.
```

---

## Prompt 5.2 — Endpoint de custos

```
Lê o CLAUDE.md. Cria o endpoint de ingestão de custos.

Rota: POST /api/v1/custos   (ability 'custos:write')
Aceita UM custo ou um LOTE: { "custos": [ {...}, {...} ] }.

Cada custo (StoreCustoApiRequest):
- descricao        (string, obrigatório)
- tipo             (obrigatório, enum: material|mao_obra|maquinaria|energia|manutencao|outro)
- valor            (numérico > 0, obrigatório)
- data             (date, obrigatório)
- referencia_externa (string, opcional — chave de idempotência)
- Ligações opcionais, cada uma por ID ou nome via ResolvedorReferencias:
  campanha, operacao, cultura, parcela, maquina, funcionario

Regras:
- Valida o tipo contra o enum real do modelo Custo. Se inválido → 422 com os valores aceites.
- Idempotência: se referencia_externa já existir num Custo, NÃO duplicar — devolver o
  existente e acrescentar um aviso "custo já registado (referencia_externa)".
- Envolve toda a inserção do lote numa transação DB. Se um item falhar, devolve os erros
  por índice e não grava nenhum (ou grava os válidos — escolhe e documenta; prefiro
  tudo-ou-nada por lote).
- Devolve 201 com { criados: [ids], ignorados: [...], avisos: [...] }.
- Usa uma API Resource (CustoResource) para o output.

Escreve testes: inserção simples, lote, tipo inválido (422), idempotência (não duplica),
ligação por nome resolvida corretamente, e ligação por nome inexistente (erro claro).
```

---

## Prompt 5.3 — Endpoint de aplicações fitofarmacêuticas (caderno de campo)

```
Lê o CLAUDE.md. Cria o endpoint que regista uma aplicação de produtos — que no modelo
de dados é uma Operacao (tipo tratamento/pulverização) com linhas OperacaoProduto.
Isto alimenta diretamente o caderno de campo DGAV.

Rota: POST /api/v1/aplicacoes   (ability 'aplicacoes:write')

Payload (StoreAplicacaoApiRequest):
- Nível operação:
  campanha            (ref por id/nome, obrigatório)
  parcela             (ref, obrigatório)   cultura (ref, opcional se derivável da parcela)
  data                (date, obrigatório)
  tipo                (default 'tratamento')
  produtor_nome, aplicador_nome, aplicador_numero_autorizacao   (DGAV)
  exploracao_concelho, exploracao_freguesia                     (DGAV)
  custo_estimado, custo_real, observacoes                       (opcionais)
  referencia_externa  (idempotência, opcional)
- produtos: array (mínimo 1), cada linha OperacaoProduto:
  produto             (ref por id / numero_autorizacao_dgav / nome, obrigatório)
  quantidade, dose, dose_unidade, area_tratada, volume_calda
  finalidade, intervalo_seguranca_dias
  estabelecimento_venda_nome, estabelecimento_venda_autorizacao
  custo_unitario, observacoes

Regras:
- custo_total de cada produto = quantidade * custo_unitario (mesma lógica da UI).
- Se o produto resolvido for do tipo 'fitofarmaceutico', exigir que tenha
  numero_autorizacao_dgav; se faltar, 422 com aviso de conformidade DGAV.
- Idempotência pela referencia_externa da operação — se já existir, devolver a operação
  existente sem criar nova.
- Tudo numa transação: cria a Operacao e todas as linhas OperacaoProduto de uma vez.
- Devolve 201 com a operação criada (OperacaoResource, incluindo os produtos) e avisos.

Escreve testes: criação completa com 2 produtos, produto resolvido por nº autorização DGAV,
falta de produtos (422), produto fitofarmacêutico sem nº DGAV (422), idempotência.
```

---

## Prompt 5.4 — Documentação e exemplos

```
Lê o CLAUDE.md. Documenta a nova API.

1. Acrescenta uma secção "API de Ingestão (v1)" ao CLAUDE.md com: autenticação por token,
   as rotas, o envelope de resposta, e a regra de idempotência via referencia_externa.

2. Cria docs/API_INGESTAO.md com:
   - Como emitir um token (comando agri:emitir-token) e como enviá-lo (header
     Authorization: Bearer <token>).
   - Exemplo de payload e de resposta para POST /api/v1/custos.
   - Exemplo de payload e de resposta para POST /api/v1/aplicacoes.
   - Um exemplo curl para cada endpoint.

3. Corre `php artisan route:list --path=api/v1` e confirma que as rotas aparecem.
```

---

## Payloads de exemplo (para colares na doc / testares)

**POST /api/v1/custos**
```json
{
  "custos": [
    {
      "descricao": "Gasóleo agrícola - tanque cheio",
      "tipo": "energia",
      "valor": 148.50,
      "data": "2026-07-15",
      "campanha": "Milho 2026",
      "maquina": "Trator John Deere 6120",
      "referencia_externa": "fatura-2026-0842"
    },
    {
      "descricao": "Jornada de mão de obra - poda",
      "tipo": "mao_obra",
      "valor": 90.00,
      "data": "2026-07-15",
      "campanha": "Milho 2026",
      "funcionario": "António Silva"
    }
  ]
}
```

**POST /api/v1/aplicacoes**
```json
{
  "campanha": "Milho 2026",
  "parcela": "Parcela Norte",
  "data": "2026-07-16",
  "tipo": "tratamento",
  "produtor_nome": "André",
  "aplicador_nome": "André",
  "aplicador_numero_autorizacao": "PT-12345",
  "exploracao_concelho": "Santarém",
  "exploracao_freguesia": "Alcanhões",
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
> No exemplo acima, `"produto": "3456"` é resolvido como número de autorização DGAV,
> ID interno, ou nome — o resolvedor tenta pela ordem definida no Prompt 5.1.

---

## Decisão que deves confirmar antes de correr

**Referências por nome vs. por ID.** A prompt está desenhada para aceitar **os dois** (ID
tem prioridade; nome/nº DGAV como alternativa), porque para enviares de fora é muito mais
fácil escrever "Milho 2026" do que saber o `campanha_id`. O risco é a ambiguidade (dois
produtos com nome parecido) — por isso o resolvedor devolve 422 com candidatos em vez de
adivinhar. Se preferires forçar sempre IDs (mais rígido, menos erros), diz e simplifico.

---
---

# BLOCO 6 — Receitas (entradas de dinheiro)

> Objetivo: registar o dinheiro que **entra** (vendas de colheita, subsídios, serviços),
> fechando o ciclo de tesouraria com os custos do Bloco 5. Não existe ainda modelo
> `Receita` — o bloco começa por criá-lo. Correr por ordem (6.1 → 6.4).

---

## Prompt 6.1 — Modelo e migration de Receita

```
Lê o CLAUDE.md. Cria o modelo e a tabela de receitas, seguindo as convenções do projeto
(nomes em português, nullOnDelete em FKs opcionais, verificar Schema::hasColumn/hasTable).

Migration `receitas`:
- id
- descricao          (string, obrigatório)
- tipo               (string, obrigatório — valores: venda_colheita | subsidio | servico | outro)
- valor              (decimal 12,2, obrigatório — valor efetivamente recebido, com IVA)
- data               (date, obrigatório)
- campanha_id        (FK nullable, nullOnDelete)
- cultura_id         (FK nullable, nullOnDelete)
- parcela_id         (FK nullable, nullOnDelete)
- colheita_id        (FK nullable, nullOnDelete)   // liga a venda a uma colheita, se aplicável
- lote_id            (FK nullable, nullOnDelete)   // só se o modelo Lote existir
- comprador_nome     (string nullable)
- documento          (string nullable)             // nº de fatura/recibo
- referencia_externa (string nullable, indexado)   // chave de idempotência
- observacoes        (text nullable)
- timestamps

Modelo App\Models\Receita:
- $fillable com todos os campos acima
- casts: data => date, valor => decimal:2
- relações: campanha(), cultura(), parcela(), colheita(), lote() (belongsTo)

No modelo Campanha, adiciona hasMany(Receita::class) e um accessor
getReceitaTotalAttribute() que soma as receitas da campanha.

Corre a migration e confirma com `php artisan tinker` que Receita::create(...) funciona.
```

---

## Prompt 6.2 — Endpoint de receitas

```
Lê o CLAUDE.md. Cria o endpoint de ingestão de receitas, no mesmo padrão do endpoint de
custos (Bloco 5): envelope JSON, resolução de referências, idempotência, transação, Resource.

Rota: POST /api/v1/receitas   (ability 'receitas:write')
Aceita UMA receita ou um LOTE: { "receitas": [ {...}, {...} ] }.

Cada receita (StoreReceitaApiRequest):
- descricao          (obrigatório)
- tipo               (obrigatório, enum: venda_colheita | subsidio | servico | outro)
- valor              (numérico > 0, obrigatório)
- data               (date, obrigatório)
- referencia_externa (opcional — idempotência)
- comprador_nome, documento, observacoes (opcionais)
- Ligações opcionais por ID ou nome (ResolvedorReferencias): campanha, cultura, parcela,
  colheita, lote

Regras:
- Valida o tipo contra o enum. Se inválido → 422 com os valores aceites.
- Idempotência: se referencia_externa já existir, não duplicar — devolver a existente com aviso.
- Transação por lote (tudo-ou-nada), igual aos custos.
- ReceitaResource para o output. Devolve 201 com { criados: [ids], ignorados, avisos }.
- Acrescenta 'receitas:write' às abilities aceites pelo comando agri:emitir-token.

Testes: inserção simples, lote, tipo inválido (422), idempotência, ligação a campanha por nome.
```

---

## Prompt 6.3 — Saldo / tesouraria (entra vs. sai)

```
Lê o CLAUDE.md. Cria uma leitura de tesouraria que junta custos (saídas) e receitas (entradas).

Serviço App\Services\TesourariaService com:
- resumo(?Campanha $campanha, ?string $de, ?string $ate):
  devolve { entradas, saidas, saldo, por_tipo_entrada, por_tipo_saida }

Rota: GET /api/v1/tesouraria   (auth:sanctum)
  Query params opcionais: campanha (id/nome), de (date), ate (date).
  Resposta: o resumo acima em JSON.

Se preferires, expõe também no dashboard Inertia existente um cartão "Entradas vs Saídas"
do ano corrente — mas não quebres o DashboardController atual.
```

---

## Prompt 6.4 — Documentação de receitas

```
Lê o CLAUDE.md. Atualiza docs/API_INGESTAO.md e a secção "API de Ingestão (v1)" do CLAUDE.md
para incluir POST /api/v1/receitas e GET /api/v1/tesouraria, com um exemplo de payload
(venda de colheita e subsídio) e um exemplo curl para cada.
```

---

## Payloads de exemplo — Receitas

**POST /api/v1/receitas** (venda de colheita + subsídio)
```json
{
  "receitas": [
    {
      "descricao": "Venda de milho - lote colheita",
      "tipo": "venda_colheita",
      "valor": 8450.00,
      "data": "2026-10-20",
      "campanha": "2025/2026",
      "comprador_nome": "Cooperativa Agrícola",
      "documento": "FT 2026/338",
      "referencia_externa": "venda-2026-10-20-milho"
    },
    {
      "descricao": "Subsídio PAC - pagamento único",
      "tipo": "subsidio",
      "valor": 3200.00,
      "data": "2026-12-05",
      "campanha": "2025/2026",
      "referencia_externa": "subsidio-pac-2026"
    }
  ]
}
```

> Nota IVA: tal como nos custos, o `valor` é o dinheiro efetivamente recebido (com IVA),
> para o saldo de tesouraria refletir o fluxo de caixa real.

---

## Prompt 6.5 — Registo de colheita e lote (rastreabilidade)

```
Lê o CLAUDE.md. PRIMEIRO inspeciona os modelos e migrations existentes de Colheita e Lote
(app/Models/Colheita.php, Lote.php e as respetivas migrations). NÃO inventes colunas —
alinha os campos ao que já existe. Só cria migration nova se faltar um campo abaixo.

Objetivo: permitir criar uma Colheita com um ou mais Lotes via API, garantindo que cada
Lote guarda a ORIGEM (terreno) e a DATA DE COLHEITA — para rastreabilidade.

Rota: POST /api/v1/colheitas   (nova ability 'colheitas:write')

Payload:
- Nível colheita:
  campanha        (ref por id/nome, obrigatório)
  cultura         (ref, obrigatório)
  parcela         (ref, opcional se derivável)
  data            (date da colheita, obrigatório)
  quantidade_total, qualidade, observacoes (conforme o modelo Colheita real)
  referencia_externa (idempotência)
- lotes: array (mínimo 1), cada Lote:
  codigo          (string; se vazio, gerar automaticamente, ex. LOTE-{campanha}-{seq})
  terreno         (ref por id/nome — ORIGEM, obrigatório para rastreabilidade)
  data_colheita   (date; se vazia, herda a data da colheita)
  quantidade, unidade
  localizacao_armazem, observacoes (se existirem no modelo)

Regras:
- Se o modelo Lote NÃO tiver coluna terreno_id nem forma direta de guardar a origem,
  cria uma migration nullable `add_terreno_id_to_lotes_table` (FK nullOnDelete) e adiciona
  ao $fillable. Mesma lógica para data_colheita se não existir.
- Resolve terreno via ResolvedorReferencias (id ou nome).
- Idempotência pela referencia_externa da colheita.
- Transação: cria a Colheita e todos os Lotes de uma vez.
- Devolve 201 com a colheita e os lotes criados (Resources).
- Acrescenta 'colheitas:write' às abilities do agri:emitir-token.

Depois disto, um Lote pode ser referenciado no POST /api/v1/receitas (campo "lote"),
ligando a venda à sua origem (terreno + data de colheita).

Testes: criar colheita com 2 lotes, geração automática de código, herança da data_colheita,
terreno inexistente (erro claro), idempotência.
```

**POST /api/v1/colheitas** (exemplo)
```json
{
  "campanha": "2025/2026",
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
> No segundo lote, `codigo` e `data_colheita` ficam vazios: o código é gerado
> automaticamente e a data herda a da colheita (2026-10-18).