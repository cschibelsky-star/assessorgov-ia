<?php

namespace App\Http\Controllers;

use App\Models\GovComplianceItem;
use Illuminate\Http\Request;

class GovComplianceReviewQueueController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->can('compliance.review'), 403);

        return view('gov.compliance-review', [
            'items' => GovComplianceItem::query()
                ->with(['customer', 'latestEvidence'])
                ->whereIn('status', ['submitted', 'in_review'])
                ->orderByRaw("case when status = 'submitted' then 0 else 1 end")
                ->latest('submitted_at')
                ->get(),
        ]);
    }
}
