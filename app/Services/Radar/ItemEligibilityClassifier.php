<?php

namespace App\Services\Radar;

use DateTimeImmutable;
use DateTimeZone;

/** Pure pre-screening of reviewed requirements; never grants legal habilitation. */
final class ItemEligibilityClassifier
{
    public function classify(array $item, array $profile, DateTimeImmutable $at): array
    {
        $activity = $item['activity'] ?? null;
        $activities = is_array($profile['activities'] ?? null) ? $profile['activities'] : [];
        $knownActivity = is_string($activity) && trim($activity) !== '';
        $fit = $knownActivity && in_array($activity, $activities, true);
        $commercial = $fit ? 'compatible' : (($profile['activities_complete'] ?? false) === true && $knownActivity ? 'outside_profile' : 'unconfirmed');
        $gaps = $fit ? [] : ['activity'];
        $documentGaps = [];
        $reasons = [$fit
            ? 'Atividade declarada compatível; habilitação exige evidências próprias.'
            : 'Fornecimento não confirmado no perfil; isso não equivale a impedimento jurídico.'];
        $requirements = is_array($item['requirements'] ?? null) ? $item['requirements'] : [];
        if (($item['requirements_reviewed'] ?? false) !== true || $requirements === []) {
            $documentGaps[] = 'requirements_review';
        }
        $customer = $profile['customer_id'] ?? null;
        if (! is_string($customer) || trim($customer) === '') {
            $documentGaps[] = 'customer_scope';
        }
        $today = $at->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        $failed = false;
        foreach ($requirements as $requirement) {
            $rule = is_string($requirement) ? ['key' => $requirement] : $requirement;
            if (! is_array($rule) || ! is_string($rule['key'] ?? null) || trim($rule['key']) === '') {
                $documentGaps[] = 'requirements_schema';

                continue;
            }
            $key = $rule['key'];
            $evidence = $profile['evidence'][$key] ?? [];
            $evidence = is_array($evidence) ? $evidence : [];
            $state = $evidence['status'] ?? 'unknown';
            $reference = $evidence['reference'] ?? null;
            $scoped = is_string($customer) && trim($customer) !== ''
                && ($evidence['customer_id'] ?? null) === $customer;
            if (($rule['scope'] ?? 'customer') === 'item') {
                $scoped = $scoped
                    && is_string($item['id'] ?? null) && trim($item['id']) !== ''
                    && ($evidence['item_id'] ?? null) === $item['id'];
            } elseif (($rule['scope'] ?? 'customer') !== 'customer') {
                $scoped = false;
            }
            $validUntil = $evidence['valid_until'] ?? null;
            $date = is_string($validUntil) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $validUntil) === 1
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $validUntil) : false;
            $validDate = $date !== false && $date->format('Y-m-d') === $validUntil;
            // No-expiry is a reviewed requirement policy, never a document's self-declaration.
            $validity = $validUntil === null && ($rule['expires'] ?? true) === false
                ? true : ($validDate && $validUntil >= $today);
            $valid = $state === 'verified' && is_string($reference) && trim($reference) !== '' && $scoped && $validity;
            if (! $valid) {
                $documentGaps[] = $key;
                $reasons[] = 'Evidência pendente, inválida ou fora de escopo: '.$key;
            }
            // An unrelated company's rejection must not block this customer's screening.
            if ($state === 'failed' && $scoped) {
                $curable = ($rule['me_epp_regularization'] ?? false) === true
                    && ($profile['me_epp_verified'] ?? false) === true;
                $failed = $failed || ! $curable;
                if ($curable) {
                    $reasons[] = 'Restrição fiscal/trabalhista requer análise de regularização ME/EPP; não autoriza participação.';
                }
            }
        }
        $eligibility = $failed ? 'blocked' : ($documentGaps === [] ? 'verified_for_prescreen' : 'pending_evidence');

        return [
            'commercial_fit' => $commercial,
            'eligibility' => $eligibility,
            'classification' => $commercial === 'outside_profile' ? 'outside_profile'
                : ($failed ? 'blocked' : ($commercial === 'compatible' && $documentGaps === [] ? 'compatible_for_analysis' : 'pending_evidence')),
            'match_score' => null,
            'match_reasons' => $reasons,
            'gaps' => array_values(array_unique(array_merge($gaps, $documentGaps))),
            'commercial_notification_allowed' => false,
            'proposal_allowed' => false,
        ];
    }
}
