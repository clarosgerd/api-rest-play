<?php

use App\Http\Controllers\AdminPlanController;
use App\Http\Controllers\AdminSubscriptionController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdvancementController;
use App\Http\Controllers\AiCompetitorController;
use App\Http\Controllers\AiPredictionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BetsController;
use App\Http\Controllers\BonusesController;
use App\Http\Controllers\BracketController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\GroupBetsController;
use App\Http\Controllers\GroupMembersController;
use App\Http\Controllers\GroupPredictionsController;
use App\Http\Controllers\GroupsController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ManualMatchController;
use App\Http\Controllers\MatchesController;
use App\Http\Controllers\OddsController;
use App\Http\Controllers\PoolController;
use App\Http\Controllers\PredictionResultsController;
use App\Http\Controllers\PredictionsController;
use App\Http\Controllers\ScoringController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentGroupController;
use App\Http\Controllers\TournamentStageController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1'], function () {

    // Auth (public)
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Public read-only endpoints
    Route::get('tournament', [TournamentController::class, 'index']);
    // Torneos disponibles por plan+temporada (16/10/2026) — tiene que
    // registrarse ANTES de la ruta con wildcard de abajo, si no Laravel
    // intenta bindear "available-for-me" como un tournament_id y da 404.
    Route::get('tournament/available-for-me', [TournamentController::class, 'availableForMe'])
        ->middleware('auth:sanctum');
    Route::get('tournament/{tournament}', [TournamentController::class, 'show']);
    Route::get('team', [TeamController::class, 'index']);
    Route::get('team/{team}', [TeamController::class, 'show']);
    Route::get('match', [MatchesController::class, 'index']);
    Route::get('match/{match}', [MatchesController::class, 'show']);
    Route::get('stage', [TournamentStageController::class, 'index']);
    Route::get('stage/{stage}', [TournamentStageController::class, 'show']);
    Route::get('group', [GroupsController::class, 'index']);
    Route::get('group/{group}', [GroupsController::class, 'show']);
    Route::get('member', [GroupMembersController::class, 'index']);
    Route::get('member/{member}', [GroupMembersController::class, 'show']);
    Route::get('prediction', [PredictionsController::class, 'index']);
    Route::get('prediction/{prediction}', [PredictionsController::class, 'show']);
    Route::get('groupprediction', [GroupPredictionsController::class, 'index']);
    Route::get('groupprediction/{groupprediction}', [GroupPredictionsController::class, 'show']);
    Route::get('predictionresults', [PredictionResultsController::class, 'index']);
    Route::get('predictionresults/{predictionresult}', [PredictionResultsController::class, 'show']);
    Route::get('transactions', [TransactionsController::class, 'index']);
    Route::get('transactions/{transaction}', [TransactionsController::class, 'show']);
    Route::get('bonuses', [BonusesController::class, 'index']);
    Route::get('bonuses/{bonus}', [BonusesController::class, 'show']);
    Route::get('odds', [OddsController::class, 'index']);
    Route::get('odds/{odd}', [OddsController::class, 'show']);
    Route::get('bets', [BetsController::class, 'index']);
    Route::get('bets/{bet}', [BetsController::class, 'show']);
    Route::get('groupbets', [GroupBetsController::class, 'index']);
    Route::get('groupbets/{groupbet}', [GroupBetsController::class, 'show']);
    Route::get('country', [CountryController::class, 'index']);

    // Leaderboard (public)
    Route::get('group/{groupId}/leaderboard', [LeaderboardController::class, 'index']);

    // Scoring dashboard (public)
    Route::get('group/{groupId}/scoring', [ScoringController::class, 'groupDashboard']);
    Route::get('group/{groupId}/scoring/user/{userId}', [ScoringController::class, 'userDashboard']);

    // Bracket (public)
    Route::get('tournament/{tournament}/bracket', [BracketController::class, 'index']);
    Route::get('tournament/{tournament}/bracket/{stage}', [BracketController::class, 'show']);

    // Tournament Groups (public - list only)
    Route::get('tournament/{tournament}/groups', [TournamentGroupController::class, 'index']);

    // Tournament Groups (public read)
    Route::get('tournament-group/{group}', [TournamentGroupController::class, 'show']);

    // Standings (public)
    Route::get('stage/{stage}/standings', [AdvancementController::class, 'standings']);
    Route::get('stage/{stage}/group/{group}/standings', [AdvancementController::class, 'groupStandings']);

    // Advancement Rules (public read)
    Route::get('stage/{stage}/advancement-rules', [AdvancementController::class, 'indexRules']);

    // Advancement (public read)
    Route::get('stage/{stage}/advancements', [AdvancementController::class, 'listAdvanced']);

    // Players (public read)
    Route::get('player', [\App\Http\Controllers\PlayerController::class, 'index']);
    Route::get('player/{player}', [\App\Http\Controllers\PlayerController::class, 'show']);

    // Sync status (public read — used by landing page)
    Route::get('sync/status', [SyncController::class, 'status']);

    // AI predictions (public read)
    Route::get('match/{match}/ai-prediction', [AiPredictionController::class, 'show']);
    Route::get('tournament/{tournament}/ai-predictions', [AiPredictionController::class, 'tournament']);
    Route::get('ai-predictions/accuracy', [AiPredictionController::class, 'accuracy']);

    // Protected routes (auth required for writes)
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // User profile
        Route::get('user', [UserController::class, 'show']);
        Route::put('user', [UserController::class, 'update']);
        Route::put('user/password', [UserController::class, 'updatePassword']);

        // Tournaments (write) — free: 2, pro: 5, premium: unlimited
        Route::post('tournament', [TournamentController::class, 'store'])
            ->middleware('subscription.limits.groups:tournaments');
        Route::put('tournament/{tournament}', [TournamentController::class, 'update']);
        Route::patch('tournament/{tournament}/toggle-active', [TournamentController::class, 'toggleActive']);
        Route::patch('tournament/{tournament}/publish', [TournamentController::class, 'publish']);
        Route::patch('tournament/{tournament}/go-live', [TournamentController::class, 'goLive']);
        Route::patch('tournament/{tournament}/close', [TournamentController::class, 'close']);
        Route::delete('tournament/{tournament}', [TournamentController::class, 'destroy']);
        Route::post('tournament/{tournament}/assign-manager', [TournamentController::class, 'assignManager']);
        Route::post('tournament/{tournament}/remove-manager', [TournamentController::class, 'removeManager']);
        Route::get('tournament/{tournament}/managers', [TournamentController::class, 'managers']);
        // Sync manual por el dueño de su torneo público (16/10/2026) —
        // autorización propia en el controller (dueño del torneo o
        // admin-de-grupo ligado a él), no requiere el middleware 'admin'
        // global que usa SyncController::sync().
        Route::post('tournament/{tournament}/sync', [SyncController::class, 'syncTournament']);

        // Manual match results (owner only)
        Route::patch('tournament/{tournamentId}/match/{matchId}/result', [ManualMatchController::class, 'updateResult']);
        Route::patch('tournament/{tournamentId}/match/{matchId}/status', [ManualMatchController::class, 'updateStatus']);
        Route::get('tournament/{tournamentId}/pending-results', [ManualMatchController::class, 'pendingResults']);

        // Tournament Stages (write)
        Route::post('stage', [TournamentStageController::class, 'store']);
        Route::post('stage/bulk', [TournamentStageController::class, 'bulkStore']);
        Route::put('stage/{stage}', [TournamentStageController::class, 'update']);
        Route::delete('stage/{stage}', [TournamentStageController::class, 'destroy']);

        // Tournament Groups (write)
        Route::post('tournament/{tournament}/group', [TournamentGroupController::class, 'store']);
        Route::put('tournament-group/{group}', [TournamentGroupController::class, 'update']);
        Route::delete('tournament-group/{group}', [TournamentGroupController::class, 'destroy']);
        Route::post('tournament-group/{group}/teams', [TournamentGroupController::class, 'addTeams']);
        Route::delete('tournament-group/{group}/teams/{team}', [TournamentGroupController::class, 'removeTeam']);
        Route::post('tournament-group/{group}/approve', [TournamentGroupController::class, 'approve']);
        Route::post('tournament-group/{group}/reject', [TournamentGroupController::class, 'reject']);

        // Advancement Rules (write)
        Route::post('stage/{stage}/advancement-rule', [AdvancementController::class, 'storeRule']);
        Route::put('advancement-rule/{rule}', [AdvancementController::class, 'updateRule']);
        Route::delete('advancement-rule/{rule}', [AdvancementController::class, 'destroyRule']);

        // Advancement (write)
        Route::post('stage/{stage}/advancements/suggest', [AdvancementController::class, 'suggest']);
        Route::put('advancement/{advancement}/confirm', [AdvancementController::class, 'confirm']);
        Route::post('stage/{stage}/advancements/confirm-all', [AdvancementController::class, 'confirmAll']);

        // Teams (write)
        Route::post('team', [TeamController::class, 'store']);
        Route::post('team/bulk', [TeamController::class, 'bulkStore']);
        Route::put('team/{team}', [TeamController::class, 'update']);
        Route::patch('team/{team}/toggle-active', [TeamController::class, 'toggleActive']);
        Route::delete('team/{team}', [TeamController::class, 'destroy']);

        // Groups (write)
        Route::post('group', [GroupsController::class, 'store'])
            ->middleware('subscription.limits.groups:members');
        // insertFirt() es la misma acción (crear un grupo propio) por otro
        // endpoint — sin el mismo middleware, free/predictor podían esquivar
        // el gate de arriba llamando a este en su lugar.
        Route::post('group/first', [GroupsController::class, 'insertFirt'])
            ->middleware('subscription.limits.groups:members');
        Route::put('group/{group}', [GroupsController::class, 'update']);
        Route::patch('group/{group}/toggle-active', [GroupsController::class, 'toggleActive']);
        Route::get('my-tournament-memberships', [GroupsController::class, 'myTournamentMemberships']);
        Route::get('tournament/{tournamentId}/my-memberships', [GroupsController::class, 'myMemberships']);
        Route::delete('group/{group}', [GroupsController::class, 'destroy']);

        // AI Competitor
        Route::post('group/{group}/ai-competitor', [AiCompetitorController::class, 'toggle']);
        Route::post('group/{group}/ai-competitor/regenerate', [AiCompetitorController::class, 'regenerate']);
        Route::get('group/{group}/ai-competitor', [AiCompetitorController::class, 'status']);
        Route::get('group/{group}/ai-competitor/prediction/{matchId}', [AiCompetitorController::class, 'prediction']);

        // Group Members (write)
        Route::post('member', [GroupMembersController::class, 'store']);
        Route::put('member/{member}', [GroupMembersController::class, 'update']);
        Route::delete('member/{member}', [GroupMembersController::class, 'destroy']);

        // Tournament join flow
        Route::post('group/{group}/join', [GroupMembersController::class, 'joinRequest']);
        Route::post('member/{member}/approve', [GroupMembersController::class, 'approve']);
        Route::post('member/{member}/reject', [GroupMembersController::class, 'reject']);
        Route::get('group/{group}/pending-members', [GroupMembersController::class, 'pending']);

        // Matches (write)
        Route::post('match', [MatchesController::class, 'store']);
        Route::post('match/bulk', [MatchesController::class, 'bulkStore']);
        Route::put('match/{match}', [MatchesController::class, 'update']);
        Route::delete('match/{match}', [MatchesController::class, 'destroy']);

        // Predictions (write) — daily limits per tier
        Route::post('prediction', [PredictionsController::class, 'store'])
            ->middleware('subscription.limits.predictions');
        Route::put('prediction/{prediction}', [PredictionsController::class, 'update']);
        Route::delete('prediction/{prediction}', [PredictionsController::class, 'destroy']);

        // Group Predictions (write) — mismo gate de suscripción que
        // POST prediction (16/10/2026): sin esto, un usuario podía vincular
        // una predicción ya creada a otro grupo sin pasar por ningún chequeo.
        Route::post('groupprediction', [GroupPredictionsController::class, 'store'])
            ->middleware('subscription.limits.predictions');
        Route::put('groupprediction/{groupprediction}', [GroupPredictionsController::class, 'update']);
        Route::delete('groupprediction/{groupprediction}', [GroupPredictionsController::class, 'destroy']);

        // Prediction Results (write)
        Route::post('predictionresults', [PredictionResultsController::class, 'store']);
        Route::put('predictionresults/{predictionresult}', [PredictionResultsController::class, 'update']);
        Route::delete('predictionresults/{predictionresult}', [PredictionResultsController::class, 'destroy']);

        // Transactions (write)
        Route::post('transactions', [TransactionsController::class, 'store']);
        Route::put('transactions/{transaction}', [TransactionsController::class, 'update']);
        Route::delete('transactions/{transaction}', [TransactionsController::class, 'destroy']);

        // Bonuses (write)
        Route::post('bonuses', [BonusesController::class, 'store']);
        Route::put('bonuses/{bonus}', [BonusesController::class, 'update']);
        Route::delete('bonuses/{bonus}', [BonusesController::class, 'destroy']);

        // Odds (write)
        Route::post('odds', [OddsController::class, 'store']);
        Route::put('odds/{odd}', [OddsController::class, 'update']);
        Route::delete('odds/{odd}', [OddsController::class, 'destroy']);

        // Bets (write)
        Route::post('bets', [BetsController::class, 'store']);
        Route::put('bets/{bet}', [BetsController::class, 'update']);
        Route::delete('bets/{bet}', [BetsController::class, 'destroy']);

        // Group Bets (write)
        Route::post('groupbets', [GroupBetsController::class, 'store']);
        Route::put('groupbets/{groupbet}', [GroupBetsController::class, 'update']);
        Route::delete('groupbets/{groupbet}', [GroupBetsController::class, 'destroy']);

        // Pool
        Route::get('pool', [PoolController::class, 'index']);
        Route::get('pool/{groupId}', [PoolController::class, 'show']);
        Route::post('pool', [PoolController::class, 'store']);
        Route::post('pool/{groupId}/distribute', [PoolController::class, 'distribute']);

        // Invitations
        Route::get('invitation', [InvitationController::class, 'index']);
        Route::post('invitation', [InvitationController::class, 'store']);
        Route::post('invitation/accept-code', [InvitationController::class, 'acceptByCode']);
        Route::post('invitation/{invitation}/accept', [InvitationController::class, 'acceptInvitation']);
        Route::delete('invitation/{invitation}', [InvitationController::class, 'destroy']);

        // Subscription
        Route::get('user/subscription', [SubscriptionController::class, 'show']);
        Route::get('user/subscription/plans', [SubscriptionController::class, 'plans']);
        Route::post('user/subscription/checkout', [SubscriptionController::class, 'checkout']);
        Route::post('user/subscription/cancel', [SubscriptionController::class, 'cancel']);
        Route::get('user/subscription/payment-status/{referencia}', [SubscriptionController::class, 'paymentStatus']);

        // Sync (admin only)
        Route::post('sync', [SyncController::class, 'sync'])->middleware('admin');
        Route::get('sync/runs/{syncRun}', [SyncController::class, 'runStatus'])->middleware('admin');
        Route::get('sync/competitions', [SyncController::class, 'competitions'])->middleware('admin');

        // Panel de soporte para super admin (17/10/2026)
        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('users', [AdminUserController::class, 'index']);
            Route::get('users/{user}', [AdminUserController::class, 'show']);
            Route::patch('users/{user}', [AdminUserController::class, 'update']);
            Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
            Route::post('users/{user}/toggle-suspended', [AdminUserController::class, 'toggleSuspended']);

            Route::get('subscriptions', [AdminSubscriptionController::class, 'index']);
            Route::post('subscriptions/{subscription}/confirm', [AdminSubscriptionController::class, 'confirm']);
            Route::get('payments', [AdminSubscriptionController::class, 'payments']);

            Route::get('plans', [AdminPlanController::class, 'index']);
            Route::post('plans', [AdminPlanController::class, 'store']);
            Route::patch('plans/{plan}', [AdminPlanController::class, 'update']);
            Route::post('plans/{plan}/promotions', [AdminPlanController::class, 'storePromotion']);
            Route::patch('plans/{plan}/promotions/{promotion}', [AdminPlanController::class, 'updatePromotion']);
        });
    });

    // Stripe webhook (no auth) — sin uso activo desde que las suscripciones se cobran por SIP, se deja por si se retoma Stripe/USD
    Route::post('webhook/stripe', [SubscriptionController::class, 'webhook']);

    // SIP callback (no auth — valida Basic Auth propio, ver SubscriptionController::sipCallback())
    Route::post('subscription/sip/callback', [SubscriptionController::class, 'sipCallback']);
});
