<?php

use App\Http\Controllers\CulturaDashboardController;
use App\Http\Controllers\CulturalOpportunityController;
use App\Http\Controllers\CulturalProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'application' => config('app.name', 'AssessorGov IA'),
        'status' => 'ok',
        'solutions' => [
            'assessorgov' => 'Compras Publicas',
            'cultura' => 'Cultura e Editais',
            'terceiro_setor' => 'Projetos Sociais e Captacao de Recursos',
        ],
    ]);
});

Route::middleware('auth')->prefix('cultura')->name('cultura.')->group(function () {
    Route::get('/', CulturaDashboardController::class)->name('dashboard');
    Route::get('/perfil', [CulturalProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [CulturalProfileController::class, 'update'])->name('profile.update');
    Route::get('/oportunidades/{opportunity}', [CulturalOpportunityController::class, 'show'])->name('opportunities.show');
});
