<?php

namespace App\Http\Controllers;

use App\Services\Gov\GovIntelligenceService;
use Illuminate\Http\Request;

class GovIntelligenceController extends Controller
{
    public function __invoke(Request $request, GovIntelligenceService $intelligence)
    {
        return view('gov.intelligence', $intelligence->forUser($request->user()));
    }
}
