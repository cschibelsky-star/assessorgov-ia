<?php

namespace App\Http\Controllers;

use App\Services\Gov\GovIntelligenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GovIntelligenceActionController extends Controller
{
    public function store(Request $request, string $item, GovIntelligenceService $intelligence): RedirectResponse
    {
        $result = $intelligence->applyToCustomer($request->user(), $item);

        if ($result['status'] === 'customer_required') {
            return redirect()->route('gov.intelligence')
                ->with('status', 'Complete o cadastro empresarial antes de aplicar esta recomendação.');
        }

        if ($result['status'] === 'no_linked_opportunity') {
            return redirect()->route('gov.compliance')
                ->with('status', 'A recomendação foi identificada, mas ainda não existe oportunidade vinculada onde ela possa ser registrada.');
        }

        return redirect()->route('gov.compliance', ['focus' => $result['item']['action_target']])
            ->with('status', 'Ação adicionada ao compliance das oportunidades afetadas.');
    }
}
