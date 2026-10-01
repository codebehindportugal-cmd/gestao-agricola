<?php

namespace App\Services\Moloni;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente minimo da API do Moloni (https://www.moloni.pt/dev/).
 *
 * Autenticacao de aplicacao nativa: grant_type=password com o Developer ID, a
 * chave secreta e o utilizador do Moloni. O access_token dura 1 hora e o
 * refresh_token 14 dias; guardam-se os dois na cache e renova-se sozinho.
 *
 * Cada chamada e um POST para {url}/{metodo}/?access_token=..., com os
 * parametros no corpo.
 */
class MoloniCliente
{
    private const CHAVE_CACHE = 'moloni.tokens';

    public function configurado(): bool
    {
        return filled(config('services.moloni.client_id'))
            && filled(config('services.moloni.client_secret'))
            && filled(config('services.moloni.username'))
            && filled(config('services.moloni.password'));
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return array<mixed>
     */
    public function chamar(string $metodo, array $parametros = []): array
    {
        $resposta = $this->pedir($metodo, $parametros, $this->accessToken());

        // Token revogado ou expirado antes do tempo: pede outro uma vez.
        if ($resposta === null) {
            Cache::forget(self::CHAVE_CACHE);
            $resposta = $this->pedir($metodo, $parametros, $this->accessToken());
        }

        if ($resposta === null) {
            throw new MoloniException("O Moloni recusou o acesso em {$metodo}.");
        }

        return $resposta;
    }

    /** Empresa a usar: a configurada, ou a unica (primeira) da conta. */
    public function companyId(): int
    {
        $configurada = config('services.moloni.company_id');

        if (filled($configurada)) {
            return (int) $configurada;
        }

        return Cache::remember('moloni.company_id', now()->addDay(), function (): int {
            $empresas = $this->chamar('companies/getAll');
            $primeira = $empresas[0]['company_id'] ?? null;

            if ($primeira === null) {
                throw new MoloniException('A conta do Moloni nao tem nenhuma empresa.');
            }

            return (int) $primeira;
        });
    }

    /**
     * @return array<mixed>|null null quando o token foi recusado
     */
    private function pedir(string $metodo, array $parametros, string $token): ?array
    {
        try {
            $resposta = Http::asForm()
                ->acceptJson()
                ->timeout(30)
                ->post($this->url($metodo).'?access_token='.urlencode($token), $parametros);
        } catch (ConnectionException $e) {
            throw new MoloniException('Nao foi possivel ligar ao Moloni: '.$e->getMessage(), 0, $e);
        }

        if (in_array($resposta->status(), [401, 403], true)) {
            return null;
        }

        $dados = $resposta->json();

        if (is_array($dados) && ($dados['error'] ?? null) === 'invalid_token') {
            return null;
        }

        if (! $resposta->successful() || ! is_array($dados)) {
            throw new MoloniException("Resposta inesperada do Moloni em {$metodo} (HTTP {$resposta->status()}).");
        }

        // O Moloni devolve erros de validacao como lista de {code, description}.
        if (isset($dados[0]['code']) && isset($dados[0]['description']) && ! isset($dados[0]['document_id'])) {
            throw new MoloniException("Moloni {$metodo}: ".$dados[0]['description']);
        }

        return $dados;
    }

    private function accessToken(): string
    {
        $tokens = Cache::get(self::CHAVE_CACHE);

        if (is_array($tokens) && ($tokens['expira'] ?? 0) > time() + 60) {
            return $tokens['access_token'];
        }

        if (is_array($tokens) && filled($tokens['refresh_token'] ?? null) && ($tokens['refresh_expira'] ?? 0) > time()) {
            $novo = $this->grant([
                'grant_type' => 'refresh_token',
                'client_id' => config('services.moloni.client_id'),
                'client_secret' => config('services.moloni.client_secret'),
                'refresh_token' => $tokens['refresh_token'],
            ], false);

            if ($novo !== null) {
                return $novo;
            }
        }

        if (! $this->configurado()) {
            throw new MoloniException('Faltam as credenciais do Moloni no .env (MOLONI_CLIENT_ID, MOLONI_CLIENT_SECRET, MOLONI_USERNAME, MOLONI_PASSWORD).');
        }

        return (string) $this->grant([
            'grant_type' => 'password',
            'client_id' => config('services.moloni.client_id'),
            'client_secret' => config('services.moloni.client_secret'),
            'username' => config('services.moloni.username'),
            'password' => config('services.moloni.password'),
        ], true);
    }

    private function grant(array $parametros, bool $falharSeRecusado): ?string
    {
        try {
            $resposta = Http::acceptJson()->timeout(30)->get($this->url('grant'), $parametros);
        } catch (ConnectionException $e) {
            throw new MoloniException('Nao foi possivel ligar ao Moloni: '.$e->getMessage(), 0, $e);
        }

        $dados = $resposta->json();

        if (! $resposta->successful() || ! is_array($dados) || empty($dados['access_token'])) {
            if (! $falharSeRecusado) {
                return null;
            }

            $motivo = is_array($dados) ? ($dados['error_description'] ?? $dados['error'] ?? '') : '';

            throw new MoloniException(trim('O Moloni recusou as credenciais. '.$motivo));
        }

        Cache::put(self::CHAVE_CACHE, [
            'access_token' => $dados['access_token'],
            'refresh_token' => $dados['refresh_token'] ?? null,
            'expira' => time() + (int) ($dados['expires_in'] ?? 3600),
            'refresh_expira' => time() + 14 * 24 * 3600 - 3600,
        ], now()->addDays(14));

        return (string) $dados['access_token'];
    }

    private function url(string $metodo): string
    {
        return rtrim((string) config('services.moloni.url'), '/').'/'.trim($metodo, '/').'/';
    }
}
