<?php

namespace App\Services\Radar;

use DateTimeImmutable;

/** Pure pre-screening of reviewed item requirements; never grants legal habilitation. */
final class ItemEligibilityClassifier
{
    public function classify(array $item, array $profile, DateTimeImmutable $at): array
    {
        $gaps = [];
        $reasons = [];
        $activity = $item['activity'] ?? null;
        $activities = $profile['activities'] ?? [];
        $fit = is_string($activity) && in_array($activity, $activities, true);
        $commercial = $fit ? 'compatible' : 'unconfirmed';

        if (! $fit) {
            $commercial = ($profile['activities_complete'] ?? false) === true
                && is_string($activity) ? 'outside_profile' : 'unconfirmed';
            $gaps[] = 'activity';
            $reasons[] = 'Fornecimento do item não consta das atividades informadas; isso não equivale a impedimento jurídico.';
        } else {
            $reasons[] = 'Atividade declarada compatível com este item; habilitação exige evidências próprias.';
        }

        $requirements = $item['requirements'] ?? [];
        if (($item['requirements_reviewed'] ?? false) !== true || $requirements === []) {
            $gaps[] = 'requirements_review';
        }

        $failed = false;
        foreach ($requirements as $key) {
            $evidence = $profile['evidence'][$key] ?? [];
            $state = $evidence['status'] ?? 'unknown';
            $validUntil = $evidence['valid_until'] ?? null;
            $validDate = is_string($validUntil)
                && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $validUntil) === 1
                && DateTimeImmutable::createFromFormat('!Y-m-d', $validUntil)->format('Y-m-d') === $validUntil;
            $valid = $state === 'verified'
                && ! empty($evidence['reference'])
                && $validDate
                && $validUntil >= $at->format('Y-m-d');
            if (! $valid) {
                $gaps[] = $key;
            }
            $failed = $failed || $state === 'failed';
        }

        $eligibility = $failed ? 'blocked' : ($gaps === [] ? 'verified_for_prescreen' : 'pending_evidence');

        return [
            'commercial_fit' => $commercial,
            'eligibility' => $eligibility,
            'classification' => $commercial === 'outside_profile' ? 'outside_profile'
                : ($failed ? 'blocked' : ($gaps === [] ? 'compatible_for_analysis' : 'pending_evidence')),
            'match_score' => null,
            'match_reasons' => $reasons,
            'gaps' => array_values(array_unique($gaps)),
            'commercial_notification_allowed' => false,
            'proposal_allowed' => false,
        ];
    }
}
