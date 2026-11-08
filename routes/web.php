<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\TrackController;
use App\Http\Controllers\AudioLibraryController;
use App\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PlanStatusController;

/*
|--------------------------------------------------------------------------
| PÁGINAS PÚBLICAS (INERTIA)
|--------------------------------------------------------------------------
| A Home "/" renderiza Dashboard e o SimbaLayout decide:
| - não logado: mostra login + register
| - logado: se plano ativo, mostra conteúdo; se não, paywall
*/
Route::get('/', function () {
    return Inertia::render('Dashboard');
})->name('home');

Route::get('/search', function () {
    return Inertia::render('Search');
})->name('search');

Route::get('/playlists', function () {
    return Inertia::render('Playlists');
})->name('playlists');


Route::middleware('auth')->get('/plan/check', [PlanStatusController::class, 'check']);
Route::middleware(['auth'])->post('/audio/delete', [AudioLibraryController::class, 'delete']);

/*
|--------------------------------------------------------------------------
| ROTAS PROTEGIDAS (auth)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // dashboard logado (a view é a mesma, o Vue decide o que exibir)
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | PERFIL
    |--------------------------------------------------------------------------
    */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | PLANO / PIX
    |--------------------------------------------------------------------------
    | Botão "Já paguei" chama essa rota para ativar/renovar o plano
    */
    Route::post('/plan/confirm-pix', [PlanController::class, 'confirmPix'])
        ->name('plan.confirmPix');

    /*
    |--------------------------------------------------------------------------
    | ACERVO / PLAYER (EXIGE PLANO ATIVO)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['plan.active'])->group(function () {

        // biblioteca local
        Route::get('/audio/tracks', [AudioLibraryController::class, 'index'])
            ->name('audio.tracks');

    });
});


/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
| Recomendo proteger também, senão alguém acessa sem pagar.
| Se alguma rota for pública, tire o middleware dela.
*/
Route::prefix('api')->middleware(['auth', 'plan.active'])->group(function () {
    Route::get('/tracks', [TrackController::class, 'index'])->name('api.tracks.index');
    Route::post('/tracks', [TrackController::class, 'store'])->name('api.tracks.store');
});


/*
|--------------------------------------------------------------------------
| AUTH (Breeze)
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';