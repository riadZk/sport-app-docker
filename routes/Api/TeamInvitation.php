<?php

use App\Http\Controllers\Api\Team\TeamInvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // --- Team-scoped (admin actions) ---
    Route::prefix('teams/{team}')->group(function () {
        // Admin sends an invitation to a player
        Route::post('/invite', [TeamInvitationController::class, 'invite'])->name('teams.invitations.invite');
        // Admin sends invitations to multiple players at once (e.g. during team creation)
        Route::post('/bulk-invite', [TeamInvitationController::class, 'bulkInvite'])->name('teams.invitations.bulk-invite');
        // Admin views all invitations & join requests for their team
        Route::get('/invitations', [TeamInvitationController::class, 'teamInvitations'])->name('teams.invitations.index');
    });

    // --- Player sends a join request to a team ---
    Route::post('/teams/{team}/join-request', [TeamInvitationController::class, 'requestJoin'])->name('teams.invitations.request-join');

    // --- Authenticated user views their own received invitations ---
    Route::get('/invitations/mine', [TeamInvitationController::class, 'myInvitations'])->name('invitations.mine');

    // --- Accept / Reject ---
    Route::post('/invitations/{invitation}/respond', [TeamInvitationController::class, 'respond'])->name('invitations.respond');

    // --- Cancel (by sender) ---
    Route::delete('/invitations/{invitation}', [TeamInvitationController::class, 'cancel'])->name('invitations.cancel');
});
