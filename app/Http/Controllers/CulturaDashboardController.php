<?php

namespace App\Http\Controllers;

use App\Models\CulturalOpportunity;
use App\Services\Cultura\CulturalOpportunityMatcher;
use Illuminate\Http\Request;

class CulturaDashboardController extends Controller
{
    public function __invoke(Request $request, CulturalOpportunityMatcher $matcher)
    {
        $user = $request->user();
        $profile = $user?->culturalProfile;
        $planSlug = 'gratuito';
        $limits = config('assessorgov_cultura.plans.' . $planSlug, []);
        $radarLimit = (int) ($limits['radar_limit'] ?? 5);

        $opportunities = CulturalOpportunity::query()
            ->saoPaulo()
            ->active()
            ->orderBy('closes_at')
            ->limit(200)
            ->get();

        if ($profile) {
            $radar = $matcher->rank($profile, $opportunities, $radarLimit);
        } else {
            $radar = $opportunities
                ->take($radarLimit)
                ->values()
                ->map(fn (CulturalOpportunity $opportunity) => [
                    'opportunity' => $opportunity,
                    'score' => null,
                    'reasons' => [],
                    'warnings' => ['complete_o_perfil_para_calcular_aderencia'],
                ]);
        }

        return view('cultura.dashboard', [
            'user' => $user,
            'profile' => $profile,
            'planSlug' => $planSlug,
            'limits' => $limits,
            'radar' => $radar,
            'activeOpportunities' => $opportunities->count(),
        ]);
    }
}
