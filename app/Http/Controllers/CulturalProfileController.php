<?php

namespace App\Http\Controllers;

use App\Models\CulturalProfile;
use Illuminate\Http\Request;

class CulturalProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('cultura.profile', [
            'profile' => $request->user()->culturalProfile,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:30'],
            'municipality' => ['required', 'string', 'max:255'],
            'cultural_areas' => ['required', 'array', 'min:1'],
            'cultural_areas.*' => ['string', 'max:100'],
            'legal_profiles' => ['required', 'array', 'min:1'],
            'legal_profiles.*' => ['string', 'max:100'],
            'territories' => ['nullable', 'array'],
            'territories.*' => ['string', 'max:150'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:99'],
            'preferred_budget_min' => ['nullable', 'numeric', 'min:0'],
            'preferred_budget_max' => ['nullable', 'numeric', 'gte:preferred_budget_min'],
            'audiences' => ['nullable', 'array'],
            'audiences.*' => ['string', 'max:150'],
        ]);

        $data['state'] = 'SP';
        $data['profile_complete'] = true;

        CulturalProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        return redirect()->route('cultura.dashboard')->with('status', 'Perfil cultural atualizado.');
    }
}
