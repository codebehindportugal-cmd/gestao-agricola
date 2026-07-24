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
            'campanha_id' => ['nullable', 'integer', 'exists:campanhas,id'],
        ]);

        if (empty($data['campanha_id'])) {
            $request->session()->forget('campanha_ativa_id');

            return back()->with('success', 'Campanha ativa removida.');
        }

        $campanha = Campanha::query()->with('cultura:id,nome')->findOrFail($data['campanha_id']);

        $request->session()->put('campanha_ativa_id', $campanha->id);

        return back()->with('success', "Campanha ativa: {$campanha->cultura?->nome} {$campanha->ano}.");
    }
}
