# Gestão Agrícola - Instruções para Claude Code

## Stack e Ambiente
- **Framework**: Laravel 11 + Inertia.js + Vue.js
- **BD**: MySQL (Laragon) — `gestao_agricola`
- **Caminho**: `c:\laragon\www\gestao-agricola`
- **PHP**: via Laragon (Windows)
- **Artisan**: `php artisan`

## Arquitetura dos Controllers
Existem **dois padrões** de controllers no projeto — não os misturar:

| Padrão | Exemplos | Retorna |
|--------|----------|---------|
| **Inertia (Web)** | `CampanhaController`, `OperacaoManagementController`, `TerrenoManagementController` | `Inertia::render(...)` |
| **API JSON** | `OperacaoController`, `TerrenoController`, `ParcelaController` | `response()->json(...)` |

Novos controllers Web usam Inertia. Novos endpoints de dados usam JSON.

## Modelos Principais e Relações

```
Terreno → Parcela → Cultura → Campanha
                           → Colheita → Lote
                  → Operacao → OperacaoProduto (pivot)
                             → Jornada
                             → Custo
Campanha → Custo (campanha_id adicionado em 2026-04-23)
Produto → OperacaoProduto
Maquina → Operacao
Funcionario → Jornada
Equipa → Operacao
```

## Campos Relevantes por Modelo

### Campanha
- `custo_estimado`, `custo_real` — custos globais da campanha
- `producao_esperada`, `producao_real` — em kg
- `getCustoPorKgAttribute()` — já existe, calcula custo/kg via operações + custos

### Operacao
- `campanha_id` — liga operação à campanha
- `custo_estimado`, `custo_real` — custo da operação individual
- `image_path` — caminho da imagem carregada (campo adicionado 23/04/2026)
- `produtor_nome`, `aplicador_nome`, `aplicador_numero_autorizacao` — dados DGAV
- `exploracao_concelho`, `exploracao_freguesia` — localização DGAV

### OperacaoProduto (pivot)
- `dose`, `dose_unidade`, `area_tratada`, `volume_calda` — dados de aplicação
- `finalidade`, `intervalo_seguranca_dias` — dados fitofarmacêuticos
- `estabelecimento_venda_nome`, `estabelecimento_venda_autorizacao` — DGAV
- `custo_unitario`, `custo_total` — custos do produto

### Produto
- `numero_autorizacao_dgav` — número oficial do produto
- `tipo` — pode ser 'fitofarmaceutico', 'fertilizante', 'semente', etc.

### Custo
- `tipo` — 'material', 'mao_obra', 'maquinaria', 'energia', 'manutencao', 'outro'
- `campanha_id`, `operacao_id`, `cultura_id`, `parcela_id`, `maquina_id`, `funcionario_id`
- `rateavel` + `base_rateio` ('kg' | 'area') — custo partilhado por várias campanhas (luz das regas, câmaras frigoríficas, IMI, seguros). Fica sem `campanha_id`; o `RateioCustosService` reparte-o pelas campanhas cujo período contém a data do custo, por omissão na proporção dos quilos colhidos. Entra no `custo_total_calculado` e no custo/kg.

### Receita
- `quantidade`, `unidade` (default 'kg'), `preco_unitario` — vendas de fruta com quilos e preço; dão o preço médio de venda e a margem por quilo
- Ligar a venda a um `lote` herda a colheita, parcela e campanha desse lote

## Convenções de Código

### Nomenclatura
- Modelos e tabelas em **português** (Campanha, Colheita, Operacao, Produto, Custo)
- Sem acentos nos nomes de métodos/variáveis PHP
- Comentários podem ser em português

### Migrations
- Sempre verificar com `Schema::hasColumn()` antes de adicionar colunas
- Usar `nullOnDelete()` em foreign keys opcionais
- Formato de nome: `2026_MM_DD_HHMMSS_descricao.php`

### Inertia Views
- Ficam em `resources/js/Pages/`
- Estrutura: `Campanhas/Index.vue`, `Campanhas/Show.vue`, etc.
- Usar `route()` helper do Ziggy para URLs

### Autorização
- Sempre usar `$this->authorize()` nos controllers Inertia
- Policies existentes: Terreno, Parcela, Cultura, Operacao, Maquina, Alfaia
- Roles: `admin`, `gestor_agricola`, `operador`, `armazem`, `consultor`

## Comandos Úteis
```bash
php artisan migrate
php artisan migrate:fresh --seed
php artisan route:list --path=api
php artisan route:list --path=campanhas
php artisan tinker
php artisan make:controller NomeController
php artisan make:migration add_campo_to_tabela_table
```

## API de Ingestao (v1)
- Autenticacao por token pessoal Sanctum no header `Authorization: Bearer <token>`.
- Emitir tokens com `php artisan agri:emitir-token {email} --nome=integracao --abilities=custos:write --abilities=aplicacoes:write --abilities=colheitas:write --abilities=receitas:write`.
- **Toda** a `/api/v1` exige token Sanctum, incluindo as leituras de cadastro. Escritas exigem tambem role `admin`, `gestor_agricola` ou `operador` (`api.write.role`).
- Ingestao: `POST /custos` exige `custos:write`; `POST /aplicacoes` exige `aplicacoes:write`; `POST /trabalhos` exige `trabalhos:write` ou `custos:write`; `POST /colheitas` exige `colheitas:write`; `POST /receitas` exige `receitas:write`.
- Leitura de cadastro (so token): `GET /terrenos` `/parcelas` `/culturas` `/operacoes` `/maquinas` `/alfaias` `/campanhas` `/funcionarios` `/equipas` `/produtos` `/tesouraria`.
- `POST /aplicacoes` aceita `parcelas` (uma operacao por parcela) e os meios `maquina`, `alfaia`, `funcionario`, `equipa`, `duracao_horas`, `combustivel_gasto_l`. O tipo por omissao e `tratamento fitossanitário`, o valor que o caderno de campo filtra.
- `POST /trabalhos` regista mao de obra: cria a Operacao, uma Jornada por pessoa e por dia, e um Custo agregado `mao_obra` (o que a tesouraria conta).
- Calendario (`compromissos`): tarefas, pagamentos (IMI, seguros, seguranca social), manutencoes e prazos legais, com recorrencia. Pagina em `/calendario` (`Calendario/Index.vue`). `GET/POST /api/v1/compromissos` e `POST /api/v1/compromissos/{id}/concluir`. Concluir com valor cria o `Custo` ligado e gera a proxima ocorrencia da serie. Comando `agri:gerar-compromissos`.
- `POST /faturas` regista faturas de compra: Despesa + FaturaItem + resolucao/criacao de Produtos + entrada em stock (via `MovimentoStockService`, partilhado com o ecra de despesas) + Custo. Idempotente por `numero_fatura`.
- Todas as respostas novas usam envelope JSON: `{ "sucesso": bool, "dados": {...}|null, "avisos": [], "erros": [] }`.
- Idempotencia: quando `referencia_externa` ja existe, o endpoint devolve o registo existente e nao cria duplicado.
- Referencias podem ser enviadas por ID ou nome/codigo; se houver ambiguidade, a API devolve 422 com candidatos.
- Tesouraria cruza `receitas` como entradas e `custos` como saidas, com filtros opcionais `campanha`, `de` e `ate`.

## Campanhas

- Uma campanha **geral** (`nome` preenchido, `cultura_id` nulo) cobre várias parcelas pela tabela `campanha_parcela`. As campanhas antigas eram uma por cultura — logo uma por parcela — e é isso que enche a lista de campanhas repetidas.
- `php artisan agri:migrar-campanhas --todos-os-anos` mostra o plano; com `--confirmar` agrupa por espécie e ano ("Pereiras 2026") e repõe operações, custos, colheitas, vendas, despesas e compromissos na campanha geral.
- Culturas sem `tipo` ficam de fora e o comando avisa quais são — preencher o tipo ou correr `agri:classificar-culturas` primeiro.

## Leitura de faturas (foto ou PDF)

- `POST /despesas/extrair-fatura` (`FaturaExtracaoController`) devolve cabeçalho e linhas de uma foto/PDF; não grava nada, é o utilizador que confirma no formulário.
- Motor: `App\Services\PaperInvoice\PaperInvoiceExtractor`, portado do gestao.ateneya.com. Usa `tesseract` (OCR), `zbarimg` (QR), `pdftotext`/`pdftoppm` (PDFs). Caminhos configuráveis em `config/paper_invoice.php` (`TESSERACT_BINARY`, `ZBARIMG_BINARY`, `PDFTOTEXT_BINARY`, `PDFTOPPM_BINARY`).
- O QR da AT traz **apenas** o cabeçalho (NIF, data, número, IVA, total) — as linhas dos produtos vêm sempre do OCR. No browser o QR continua a ser lido por `useQRScanner` (jsQR) e ganha ao OCR nos campos que traz.
- Em Plesk, se `proc_open` estiver em `disable_functions` nenhum destes programas corre e a resposta explica-o nos avisos.
- O extractor sugere o `produto_id` do catálogo quando o nome ou código interno aparece na descrição lida.
- **Leitura assistida**: `LeituraFatura` corre o OCR e, só quando não encontra linhas ou as linhas não somam o total, manda a imagem (ou o PDF inteiro) ao modelo de visão do Claude — `LeitorFaturaClaude`, `ANTHROPIC_API_KEY` + `CLAUDE_INVOICE_MODEL` em `config/paper_invoice.php`. Fotos de papel amarrotado são ilegíveis para o tesseract; é para essas que existe. Sem chave, fica-se pelo OCR e di-lo nos avisos. O QR, quando lido, continua a mandar no número, data e total; o nome do fornecedor vem do modelo.

## Funcionalidades em Desenvolvimento

### 1. Custos por Campanha
- Agregar `Custo::where('campanha_id', $id)` + `Operacao::where('campanha_id', $id)->sum('custo_real')`
- Discriminar por tipo: material, mão de obra, maquinaria
- Calcular custo/kg = total_custos / colheitas.sum('quantidade_total')
- Calcular rentabilidade = receita_estimada - custo_real

### 2. Extração de Dados de Imagem
- Campo `image_path` em `operacoes` — imagem da ficha de aplicação fitofarmacêutica
- Objectivo: extrair dados da imagem para preencher `OperacaoProduto`
- Usar API Claude (claude-sonnet-4-20250514) com vision para extrair campos
- Campos a extrair: produto, dose, área tratada, volume de calda, finalidade, aplicador, data

## Ficheiros de Referência
- `DATABASE_SCHEMA.md` — diagrama ER completo
- `MVP_IMPLEMENTATION.md` — o que está feito e próximos passos
- `API_DOCUMENTATION.md` — documentação dos endpoints existentes
