<?php

namespace App\Http\Controllers;

use App\Models\CulturalOpportunity;
use App\Services\Cultura\CulturalOpportunityMatcher;
use App\Services\Cultura\CulturaPlanResolver;
use Illuminate\Http\Request;

class CulturaDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CulturalOpportunityMatcher $matcher,
        CulturaPlanResolver $planResolver
    ) {
        $user = $request->user();
        $profile = $user?->culturalProfile;
        $plan = $planResolver->resolve($user);
        $planSlug = $plan['slug'];
        $limits = $plan['limits'];
        $radarLimit = $limits['radar_limit'] ?? 5;

        $opportunities = CulturalOpportunity::query()
            ->saoPaulo()
            ->active()
            ->orderBy('closes_at')
            ->limit(200)
            ->get();

        $effectiveLimit = $radarLimit === null ? $opportunities->count() : (int) $radarLimit;

        if ($profile) {
            $radar = $matcher->rank($profile, $opportunities, $effectiveLimit);
        } else {
            $radar = $opportunities
                ->take($effectiveLimit)
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
