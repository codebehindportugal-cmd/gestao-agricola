<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reduz as fotografias antes de elas chegarem ao disco.
 *
 * Uma foto de telemovel de uma fatura sao 3 a 5 MB; o ecra mostra-a com 1200
 * px de largura e o leitor automatico nao precisa de mais. Guardar o original
 * e' pagar disco, backup e largura de banda por pixeis que ninguem ve — e com
 * centenas de fotos por evento isso deixa de ser um detalhe.
 *
 * Feito so' com a GD, que vem com o PHP: uma dependencia nova obrigava a mexer
 * no composer do servidor para uma coisa que a linguagem ja' faz.
 *
 * Regras:
 *   - o que nao e' imagem (um PDF de fatura) passa incolume;
 *   - nunca amplia, e nunca guarda um resultado maior que o original;
 *   - se a compressao falhar por qualquer razao, guarda o original. Perder a
 *     fotografia de uma fatura para poupar 2 MB seria um mau negocio.
 */
class CompressorImagens
{
    /** Tipos que sabemos abrir. O resto (pdf, heic sem suporte) passa na mesma. */
    private const SUPORTADOS = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * Guarda o ficheiro, comprimido quando da'.
     *
     * Devolve o caminho relativo ao disco, como o `store()` do Laravel — quem
     * chama nao precisa de saber se houve compressao ou nao.
     */
    public function guardar(UploadedFile $ficheiro, string $pasta, string $disco = 'public'): string
    {
        if (! $this->vale($ficheiro)) {
            return $ficheiro->store($pasta, $disco);
        }

        try {
            $resultado = $this->comprimirBinario(
                (string) file_get_contents($ficheiro->getRealPath()),
                $ficheiro->getRealPath()
            );
        } catch (Throwable $excepcao) {
            Log::warning('compressao de imagem falhou; guardado o original', [
                'ficheiro' => $ficheiro->getClientOriginalName(),
                'erro' => $excepcao->getMessage(),
            ]);

            return $ficheiro->store($pasta, $disco);
        }

        if ($resultado === null || $resultado['bytes'] >= $ficheiro->getSize()) {
            // Ja' estava bem comprimido: mexer nele so' lhe tirava qualidade.
            return $ficheiro->store($pasta, $disco);
        }

        $caminho = rtrim($pasta, '/').'/'.Str::random(40).'.'.$resultado['extensao'];

        Storage::disk($disco)->put($caminho, $resultado['conteudo']);

        return $caminho;
    }

    /**
     * Comprime um ficheiro que ja' esta' no disco, no lugar.
     *
     * Usado pelo agri:comprimir-fotos para o que foi guardado antes disto
     * existir. Devolve o que mudou, ou null se nao valia a pena mexer.
     *
     * @return array{caminho: string, antes: int, depois: int}|null
     */
    public function comprimirNoDisco(string $caminho, string $disco = 'public'): ?array
    {
        $armazem = Storage::disk($disco);

        if (! $armazem->exists($caminho)) {
            return null;
        }

        $antes = (int) $armazem->size($caminho);

        if ($antes < config('imagens.minimo_bytes')) {
            return null;
        }

        $conteudo = (string) $armazem->get($caminho);

        if (! in_array($this->tipoDoBinario($conteudo), self::SUPORTADOS, true)) {
            return null;
        }

        $resultado = $this->comprimirBinario($conteudo);

        if ($resultado === null || $resultado['bytes'] >= $antes) {
            return null;
        }

        $novoCaminho = $this->trocarExtensao($caminho, $resultado['extensao']);

        $armazem->put($novoCaminho, $resultado['conteudo']);

        if ($novoCaminho !== $caminho) {
            $armazem->delete($caminho);
        }

        return ['caminho' => $novoCaminho, 'antes' => $antes, 'depois' => $resultado['bytes']];
    }

    private function vale(UploadedFile $ficheiro): bool
    {
        if (! config('imagens.comprimir')) {
            return false;
        }

        if ($ficheiro->getSize() < config('imagens.minimo_bytes')) {
            return false;
        }

        return in_array((string) $ficheiro->getMimeType(), self::SUPORTADOS, true);
    }

    /**
     * @param  string|null  $caminhoOriginal  para ler a orientacao EXIF, que so'
     *                                        existe no ficheiro e nao no binario ja' lido
     * @return array{conteudo: string, bytes: int, extensao: string}|null
     */
    private function comprimirBinario(string $conteudo, ?string $caminhoOriginal = null): ?array
    {
        $memoria = config('imagens.memoria');

        if (filled($memoria)) {
            @ini_set('memory_limit', (string) $memoria);
        }

        $imagem = @imagecreatefromstring($conteudo);

        if (! $imagem instanceof GdImage) {
            return null;
        }

        try {
            $imagem = $this->corrigirOrientacao($imagem, $caminhoOriginal);
            $imagem = $this->redimensionar($imagem);

            return $this->codificar($imagem);
        } finally {
            if ($imagem instanceof GdImage) {
                imagedestroy($imagem);
            }
        }
    }

    /**
     * A foto tirada de lado vem com a rotacao so' no EXIF; a GD ignora-o e a
     * imagem sai deitada. E como o EXIF nao sobrevive a recompressao, tem de
     * ser aplicado agora ou perde-se.
     */
    private function corrigirOrientacao(GdImage $imagem, ?string $caminho): GdImage
    {
        if ($caminho === null || ! function_exists('exif_read_data')) {
            return $imagem;
        }

        $exif = @exif_read_data($caminho);
        $orientacao = (int) ($exif['Orientation'] ?? 0);

        $graus = match ($orientacao) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($graus === 0) {
            return $imagem;
        }

        $rodada = imagerotate($imagem, $graus, 0);

        if (! $rodada instanceof GdImage) {
            return $imagem;
        }

        imagedestroy($imagem);

        return $rodada;
    }

    private function redimensionar(GdImage $imagem): GdImage
    {
        $largura = imagesx($imagem);
        $altura = imagesy($imagem);
        $maximo = (int) config('imagens.lado_maximo');
        $maior = max($largura, $altura);

        if ($maior <= $maximo) {
            return $imagem;
        }

        $escala = $maximo / $maior;
        $novaLargura = max(1, (int) round($largura * $escala));
        $novaAltura = max(1, (int) round($altura * $escala));

        $destino = imagescale($imagem, $novaLargura, $novaAltura, IMG_BICUBIC);

        if (! $destino instanceof GdImage) {
            return $imagem;
        }

        imagedestroy($imagem);

        return $destino;
    }

    /** @return array{conteudo: string, bytes: int, extensao: string}|null */
    private function codificar(GdImage $imagem): ?array
    {
        $qualidade = (int) config('imagens.qualidade');
        $formato = (string) config('imagens.formato');

        $usarWebp = $formato === 'webp' && function_exists('imagewebp');

        ob_start();

        if ($usarWebp) {
            // Preserva transparencia; o JPEG obrigaria a achatar sobre branco.
            imagepalettetotruecolor($imagem);
            imagealphablending($imagem, true);
            imagesavealpha($imagem, true);
            $ok = imagewebp($imagem, null, $qualidade);
            $extensao = 'webp';
        } else {
            $ok = imagejpeg($this->sobreBranco($imagem), null, $qualidade);
            $extensao = 'jpg';
        }

        $conteudo = (string) ob_get_clean();

        if (! $ok || $conteudo === '') {
            return null;
        }

        return ['conteudo' => $conteudo, 'bytes' => strlen($conteudo), 'extensao' => $extensao];
    }

    /** Um PNG transparente gravado em JPEG sai com o fundo preto se nao se fizer isto. */
    private function sobreBranco(GdImage $imagem): GdImage
    {
        $largura = imagesx($imagem);
        $altura = imagesy($imagem);

        $fundo = imagecreatetruecolor($largura, $altura);

        if (! $fundo instanceof GdImage) {
            return $imagem;
        }

        imagefill($fundo, 0, 0, imagecolorallocate($fundo, 255, 255, 255));
        imagecopy($fundo, $imagem, 0, 0, 0, 0, $largura, $altura);

        return $fundo;
    }

    private function tipoDoBinario(string $conteudo): ?string
    {
        $info = @getimagesizefromstring($conteudo);

        return $info['mime'] ?? null;
    }

    private function trocarExtensao(string $caminho, string $extensao): string
    {
        $base = preg_replace('/\.[^.\/]+$/', '', $caminho);

        return $base.'.'.$extensao;
    }
}
