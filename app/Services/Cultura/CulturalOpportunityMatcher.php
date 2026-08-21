<?php

namespace App\Services\Cultura;

use App\Models\CulturalOpportunity;
use App\Models\CulturalProfile;
use Illuminate\Support\Collection;

class CulturalOpportunityMatcher
{
    public function score(CulturalProfile $profile, CulturalOpportunity $opportunity): array
    {
        $score = 0;
        $reasons = [];
        $warnings = [];

        $profileAreas = collect($profile->cultural_areas ?? [])->map(fn ($v) => mb_strtolower((string) $v));
        $opportunityAreas = collect($opportunity->cultural_areas ?? [])->map(fn ($v) => mb_strtolower((string) $v));
        if ($profileAreas->intersect($opportunityAreas)->isNotEmpty()) {
            $score += 35;
            $reasons[] = 'area_cultural_compativel';
        } elseif ($opportunityAreas->isNotEmpty()) {
            $warnings[] = 'area_cultural_sem_correspondencia';
        }

        $legalProfiles = collect($profile->legal_profiles ?? [])->map(fn ($v) => mb_strtolower((string) $v));
        $eligible = collect($opportunity->eligible_legal_profiles ?? [])->map(fn ($v) => mb_strtolower((string) $v));
        if ($eligible->isEmpty() || $legalProfiles->intersect($eligible)->isNotEmpty()) {
            $score += 25;
            $reasons[] = 'perfil_juridico_compativel';
        } else {
            $warnings[] = 'perfil_juridico_pode_ser_incompativel';
        }

        $municipalities = collect($opportunity->municipalities ?? [])->map(fn ($v) => mb_strtolower((string) $v));
        $municipality = mb_strtolower((string) $profile->municipality);
        if ($municipalities->isEmpty() || $municipalities->contains($municipality) || $municipalities->contains('estado de sao paulo')) {
            $score += 20;
            $reasons[] = 'territorio_compativel';
        } else {
            $warnings[] = 'territorio_requer_verificacao';
        }

        $preferredMin = $profile->preferred_budget_min !== null ? (float) $profile->preferred_budget_min : null;
        $preferredMax = $profile->preferred_budget_max !== null ? (float) $profile->preferred_budget_max : null;
        $fundingMin = $opportunity->funding_min !== null ? (float) $opportunity->funding_min : null;
        $fundingMax = $opportunity->funding_max !== null ? (float) $opportunity->funding_max : null;
        if ($this->rangesOverlap($preferredMin, $preferredMax, $fundingMin, $fundingMax)) {
            $score += 10;
            $reasons[] = 'faixa_financeira_compativel';
        }

        if ($opportunity->closes_at === null || $opportunity->closes_at->isFuture()) {
            $score += 10;
            $reasons[] = 'prazo_aberto';
        }

        return [
            'score' => min($score, 100),
            'reasons' => $reasons,
            'warnings' => $warnings,
        ];
    }

    public function rank(CulturalProfile $profile, Collection $opportunities, int $limit): Collection
    {
        return $opportunities
            ->map(function (CulturalOpportunity $opportunity) use ($profile) {
                return ['opportunity' => $opportunity, ...$this->score($profile, $opportunity)];
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    private function rangesOverlap(?float $aMin, ?float $aMax, ?float $bMin, ?float $bMax): bool
    {
        if (($aMin === null && $aMax === null) || ($bMin === null && $bMax === null)) {
            return true;
        }

        $aMin ??= 0;
        $bMin ??= 0;
        $aMax ??= PHP_FLOAT_MAX;
        $bMax ??= PHP_FLOAT_MAX;

        return $aMin <= $bMax && $bMin <= $aMax;
    }
}
