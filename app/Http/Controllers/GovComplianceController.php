<?php

namespace App\Http\Controllers;

use App\Services\Gov\GovIntelligenceService;
use Illuminate\Http\Request;

class GovComplianceController extends Controller
{
    public function __invoke(Request $request, GovIntelligenceService $intelligence)
    {
        return view('gov.compliance', $intelligence->complianceForUser($request->user()));
    }
}
