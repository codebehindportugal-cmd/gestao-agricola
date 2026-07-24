# PROMPT-MESTRE — Execução por fases (API de Ingestão)

> Cola este texto no Claude Code. Requer que o ficheiro `PROMPT_API_INGESTAO.md`
> esteja no projeto (raiz ou `docs/`), pois é dele que vêm os detalhes de cada fase.

---

```
Lê o CLAUDE.md e o ficheiro PROMPT_API_INGESTAO.md deste projeto.

Vais implementar a API de ingestão (Blocos 5 e 6) executando as fases POR ORDEM, mas
PARANDO no fim de cada fase. NÃO avances para a fase seguinte sem eu escrever "continuar".

Ordem de execução:
  Fase 1  → Prompt 5.1  (base API, Sanctum, ResolvedorReferencias)
  Fase 2  → Prompt 5.2  (endpoint custos)
  Fase 3  → Prompt 5.3  (endpoint aplicações / caderno DGAV)
  Fase 4  → Prompt 5.4  (docs Bloco 5)
  Fase 5  → Prompt 6.1  (modelo + migration Receita)
  Fase 6  → Prompt 6.5  (endpoint colheitas/lotes — rastreabilidade)
  Fase 7  → Prompt 6.2  (endpoint receitas)
  Fase 8  → Prompt 6.3  (tesouraria: entradas vs saídas)
  Fase 9  → Prompt 6.4  (docs Bloco 6)

Regras para CADA fase:
1. Antes de escrever código, resume em 3-5 linhas o que vais fazer nesta fase e que
   ficheiros vais criar/alterar. Se detetares que algo já existe ou tem nome diferente
   do previsto, avisa-me em vez de assumir.
2. Implementa a fase.
3. Corre os testes dessa fase (`php artisan test`) e mostra o resultado.
4. Se algum teste falhar, PÁRA e mostra-me o erro. Não avances nem tentes "arranjar" a
   fase seguinte por cima de uma falha.
5. Se passar, faz um commit git com mensagem clara (ex.: "feat(api): fase 1 - base + auth")
   e escreve um resumo curto: o que ficou feito, que rotas/ficheiros novos, e o que testar
   manualmente (com um exemplo curl quando fizer sentido).
6. Termina com: "Fase X concluída. Escreve 'continuar' para a fase seguinte."

Restrições gerais:
- Segue o padrão JSON já existente no projeto (não uses Inertia nos endpoints da API).
- Nomes de modelos/tabelas em português, conforme o CLAUDE.md.
- Nunca corras migrate:fresh nem apagues dados. Só migrations aditivas e reversíveis.
- Verifica sempre com Schema::hasColumn/hasTable antes de adicionar colunas.
- Se precisares de instalar um pacote (ex. Sanctum), avisa antes e mostra o comando.

Começa agora pela FASE 1 (Prompt 5.1). Para no fim e espera pelo meu "continuar".
```

---

## Como conduzir (do teu lado)

- Corres isto **uma vez**. O agente faz a Fase 1, corre os testes, faz commit e pára.
- Tu revês, e escreves **`continuar`** para ele passar à fase seguinte. E assim por diante.
- Se ele parar com um erro, cola-me aqui o erro (ou diz-me o que ele reportou) e digo-te
  o próximo passo antes de escreveres "continuar".

## Se uma fase correr mal
- Como há commit no fim de cada fase, podes voltar atrás com:
  `git reset --hard HEAD~1`  (desfaz a última fase)
  e repetir só essa fase com o prompt individual correspondente do PROMPT_API_INGESTAO.md.