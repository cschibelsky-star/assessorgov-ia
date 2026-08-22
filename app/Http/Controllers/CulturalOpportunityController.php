<?php

namespace App\Http\Controllers;

use App\Models\CulturalOpportunity;
use App\Services\Cultura\CulturalOpportunityMatcher;
use App\Services\Cultura\CulturaPlanResolver;
use Illuminate\Http\Request;

class CulturalOpportunityController extends Controller
{
    public function show(
        Request $request,
        CulturalOpportunity $opportunity,
        CulturalOpportunityMatcher $matcher,
        CulturaPlanResolver $planResolver
    ) {
        abort_unless($opportunity->state === 'SP' && $opportunity->status === 'active', 404);

        $profile = $request->user()?->culturalProfile;
        $match = $profile ? $matcher->score($profile, $opportunity) : null;
        $plan = $planResolver->resolve($request->user());

        return view('cultura.opportunity', [
            'opportunity' => $opportunity,
            'match' => $match,
            'planSlug' => $plan['slug'],
            'limits' => $plan['limits'],
            'canUseAi' => (bool) ($plan['limits']['ai_analysis'] ?? false),
        ]);
    }
}
