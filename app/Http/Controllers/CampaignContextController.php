<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampaignContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campanha_ano' => ['required', 'integer', 'exists:campanhas,ano'],
        ]);

        $request->session()->put('campanha_ativa_ano', (int) $data['campanha_ano']);
        $request->session()->forget('campanha_ativa_id');

        return back()->with('success', 'Campanha ativa: '.$this->seasonLabel((int) $data['campanha_ano']).'.');
    }

    private function seasonLabel(int $ano): string
    {
        return 'Campanha '.($ano - 1).'/'.$ano;
    }
}
