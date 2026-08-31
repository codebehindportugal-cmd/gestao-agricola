<?php

namespace App\Http\Requests;

use App\Models\Compromisso;

class UpdateCompromissoRequest extends StoreCompromissoRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Compromisso::class) ?? false;
    }
}
