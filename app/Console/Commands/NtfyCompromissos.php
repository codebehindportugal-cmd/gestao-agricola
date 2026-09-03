<?php

namespace App\Console\Commands;

use App\Models\Compromisso;
use App\Models\CompromissoAviso;
use App\Support\Ntfy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Avisa pelo ntfy os compromissos do calendario que estao a chegar.
 *
 * Corre uma vez por dia. Envia uma unica notificacao com tudo o que ha nesse
 * dia e guarda em compromisso_avisos o que ja saiu, para nao repetir amanha o
 * aviso de hoje.
 */
class NtfyCompromissos extends Command
{
    protected $signature = 'ntfy:compromissos
        {--data= : Simula outro dia (Y-m-d), util para testar}
        {--seco : Mostra o que enviaria sem enviar nem gravar}
        {--reenviar : Ignora os avisos ja enviados}';

    protected $description = 'Envia para o telemovel os compromissos do calendario que estao a chegar ou em atraso';

    public function handle(): int
    {
        $seco = (bool) $this->option('seco');

        if (! $seco) {
            if (! config('ntfy.enabled')) {
                $this->error('NTFY_ENABLED esta a false.');

                return self::FAILURE;
            }

            if (blank(config('ntfy.topic'))) {
                $this->error('Falta NTFY_TOPIC no .env. Nada foi enviado.');

                return self::FAILURE;
            }

            // Desligado de proposito no .env: nao e erro, nao deve encher o log
            // com falhas todas as manhas.
            if (! config('ntfy.avisos.compromissos', true)) {
                $this->info('NTFY_AVISA_COMPROMISSOS esta a false; nada a enviar.');

                return self::SUCCESS;
            }
        }

        $hoje = CarbonImmutable::parse($this->option('data') ?: now()->toDateString())->startOfDay();
        $marcos = collect(config('ntfy.marcos_dias', [30, 7, 1]))
            ->map(fn ($dias) => (int) $dias)
            ->filter(fn (int $dias) => $dias > 0)
            ->sort()
            ->values();

        $pendentes = $this->porAvisar($hoje, $marcos);

        if ($pendentes->isEmpty()) {
            $this->info('Nada para avisar em '.$hoje->toDateString().'.');

            return self::SUCCESS;
        }

        [$titulo, $mensagem, $temAtraso] = $this->compor($pendentes, $hoje);

        $this->line($titulo);
        $this->line($mensagem);

        if ($seco) {
            $this->comment('(--seco: nada foi enviado nem gravado)');

            return self::SUCCESS;
        }

        $link = $this->urlCalendario();
        $enviado = $temAtraso
            ? Ntfy::atraso('compromissos', $titulo, $mensagem, $link)
            : Ntfy::aviso('compromissos', $titulo, $mensagem, $link);

        if (! $enviado) {
            $this->error('Nao foi enviado; nada foi marcado como avisado. Ve o laravel.log.');

            return self::FAILURE;
        }

        // So se grava depois do envio confirmado: se falhar, tenta outra vez amanha.
        foreach ($pendentes as $item) {
            CompromissoAviso::query()->updateOrCreate(
                ['compromisso_id' => $item['compromisso']->id, 'dias_antes' => $item['dias_antes']],
                ['enviado_em' => now()]
            );
        }

        $this->info('Enviado: '.$pendentes->count().' compromisso(s).');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int,int>  $marcos
     * @return Collection<int,array{compromisso:Compromisso,dias_antes:int}>
     */
    private function porAvisar(CarbonImmutable $hoje, Collection $marcos): Collection
    {
        $datasAlvo = $marcos->mapWithKeys(
            fn (int $dias) => [$hoje->addDays($dias)->toDateString() => $dias]
        );

        // whereDate e nao whereIn: a coluna tem cast de data e o valor guardado
        // pode trazer a hora, o que faz a comparacao literal nunca bater.
        $aChegar = Compromisso::query()
            ->pendentes()
            ->where(function ($query) use ($datasAlvo) {
                foreach ($datasAlvo->keys() as $data) {
                    $query->orWhereDate('data', $data);
                }
            })
            ->orderBy('data')
            ->get()
            ->map(fn (Compromisso $c) => [
                'compromisso' => $c,
                'dias_antes' => $datasAlvo->get($c->data->toDateString()),
            ]);

        $atrasados = Compromisso::query()
            ->pendentes()
            ->whereDate('data', '<', $hoje->toDateString())
            ->orderBy('data')
            ->get()
            ->map(fn (Compromisso $c) => [
                'compromisso' => $c,
                'dias_antes' => CompromissoAviso::ATRASO,
            ]);

        $todos = $atrasados->concat($aChegar);

        if ((bool) $this->option('reenviar')) {
            return $todos->values();
        }

        $jaAvisados = CompromissoAviso::query()
            ->whereIn('compromisso_id', $todos->pluck('compromisso.id')->all())
            ->get()
            ->map(fn (CompromissoAviso $aviso) => $aviso->compromisso_id.':'.$aviso->dias_antes)
            ->all();

        return $todos
            ->reject(fn (array $item) => in_array(
                $item['compromisso']->id.':'.$item['dias_antes'],
                $jaAvisados,
                true
            ))
            ->values();
    }

    /**
     * @param  Collection<int,array{compromisso:Compromisso,dias_antes:int}>  $pendentes
     * @return array{0:string,1:string,2:bool}
     */
    private function compor(Collection $pendentes, CarbonImmutable $hoje): array
    {
        $grupos = $pendentes->groupBy('dias_antes')->sortKeys();
        $linhas = [];

        foreach ($grupos as $dias => $itens) {
            $linhas[] = '**'.$this->cabecalhoGrupo((int) $dias).'**';

            foreach ($itens as $item) {
                $linhas[] = '- '.$this->linha($item['compromisso'], $hoje);
            }

            $linhas[] = '';
        }

        $total = $pendentes->count();
        $titulo = 'Gestao Agricola - '.$total.($total === 1 ? ' compromisso' : ' compromissos');

        return [$titulo, trim(implode("\n", $linhas)), $grupos->has(CompromissoAviso::ATRASO)];
    }

    private function cabecalhoGrupo(int $dias): string
    {
        return match ($dias) {
            CompromissoAviso::ATRASO => 'Em atraso',
            1 => 'Amanha',
            default => 'Daqui a '.$dias.' dias',
        };
    }

    private function linha(Compromisso $compromisso, CarbonImmutable $hoje): string
    {
        $partes = [$compromisso->titulo, $compromisso->data->format('d/m/Y')];

        if ($compromisso->hora) {
            $partes[] = substr((string) $compromisso->hora, 0, 5);
        }

        if ($compromisso->valor !== null) {
            $partes[] = number_format((float) $compromisso->valor, 2, ',', ' ').' EUR';
        }

        if ($compromisso->entidade) {
            $partes[] = $compromisso->entidade;
        }

        if ($compromisso->data->startOfDay()->lessThan($hoje)) {
            $dias = (int) $compromisso->data->startOfDay()->diffInDays($hoje);
            $partes[] = 'ha '.$dias.($dias === 1 ? ' dia' : ' dias');
        }

        return implode(' - ', $partes);
    }

    private function urlCalendario(): ?string
    {
        try {
            return route('app.calendario.index');
        } catch (\Throwable) {
            return null;
        }
    }
}
