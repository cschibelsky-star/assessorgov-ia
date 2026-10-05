<?php

namespace App\Http\Controllers;

use App\Models\GovComplianceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GovComplianceReviewController extends Controller
{
    public function update(Request $request, GovComplianceItem $complianceItem): RedirectResponse
    {
        abort_unless($request->user()->can('compliance.review'), 403);

        $data = $request->validate([
            'status' => ['required', 'in:in_review,conformant,rejected'],
            'review_note' => ['nullable', 'string', 'max:3000'],
        ]);

        abort_unless(
            in_array($complianceItem->status, ['submitted', 'in_review'], true),
            422,
            'O item precisa estar submetido ou em análise para revisão.',
        );

        $changes = [
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by_user_id' => $request->user()->getKey(),
            'reviewed_at' => now(),
            'updated_by_user_id' => $request->user()->getKey(),
        ];

        if ($data['status'] === 'conformant') {
            $changes['resolved_at'] = now();
        }

        if ($data['status'] === 'rejected') {
            $changes['resolved_at'] = null;
        }

        $complianceItem->forceFill($changes)->save();

        return redirect()->route('gov.compliance.review.index')
            ->with('status', 'Revisão de compliance atualizada.');
    }
}
