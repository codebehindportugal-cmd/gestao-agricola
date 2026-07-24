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
- Rotas protegidas em `/api/v1`: `POST /custos` exige `custos:write`; `POST /aplicacoes` exige `aplicacoes:write`; `POST /colheitas` exige `colheitas:write`; `POST /receitas` exige `receitas:write`; `GET /tesouraria` exige autenticacao Sanctum.
- Todas as respostas novas usam envelope JSON: `{ "sucesso": bool, "dados": {...}|null, "avisos": [], "erros": [] }`.
- Idempotencia: quando `referencia_externa` ja existe, o endpoint devolve o registo existente e nao cria duplicado.
- Referencias podem ser enviadas por ID ou nome/codigo; se houver ambiguidade, a API devolve 422 com candidatos.
- Tesouraria cruza `receitas` como entradas e `custos` como saidas, com filtros opcionais `campanha`, `de` e `ate`.

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
