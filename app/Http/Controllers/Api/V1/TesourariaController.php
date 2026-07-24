<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Services\ResolvedorReferencias;
use App\Services\TesourariaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TesourariaController extends Controller
{
    use RespondeJson;

    public function __construct(
        private readonly TesourariaService $tesouraria,
        private readonly ResolvedorReferencias $resolvedor
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'campanha' => ['nullable'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
        ]);

        if ($validator->fails()) {
            return $this->erro422($validator->errors()->toArray());
        }

        $data = $validator->validated();

        try {
            $campanha = array_key_exists('campanha', $data) && $data['campanha'] !== null && $data['campanha'] !== ''
                ? $this->resolvedor->resolverCampanha($this->valorReferencia($data['campanha']))
                : null;
        } catch (ValidationException $exception) {
            return $this->erro422($exception->errors());
        }

        return $this->ok($this->tesouraria->resumo(
            $campanha,
            $data['de'] ?? null,
            $data['ate'] ?? null
        ));
    }

    private function valorReferencia(mixed $referencia): int|string|null
    {
        if (! is_array($referencia)) {
            return $referencia;
        }

        foreach (['id', 'nome'] as $chave) {
            if (array_key_exists($chave, $referencia) && $referencia[$chave] !== null && $referencia[$chave] !== '') {
                return $referencia[$chave];
            }
        }

        return null;
    }
}
