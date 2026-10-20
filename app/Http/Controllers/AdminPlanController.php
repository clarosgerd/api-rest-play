<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanPromotion;
use Illuminate\Http\Request;

/**
 * CRUD de planes/promocionales para super admin (20/10/2026, pedido real del
 * usuario) — mismo patrón que AdminUserController/AdminSubscriptionController
 * (validación inline, sin FormRequest aparte). No hay delete real de un plan:
 * `is_active=false` lo "retira" sin romper una suscripción vieja que todavía
 * lo resuelve por slug (ver User::getTierLimits()).
 */
class AdminPlanController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Plan::with('promotions')->orderBy('sort_order')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:50|unique:plans,slug',
            'name' => 'required|string|max:100',
            'monthly_price_usd' => 'nullable|numeric|min:0',
            'annual_price_usd' => 'nullable|numeric|min:0',
            'monthly_price_bob' => 'nullable|numeric|min:0',
            'annual_price_bob' => 'nullable|numeric|min:0',
            'stripe_price_id_monthly' => 'nullable|string|max:255',
            'stripe_price_id_annual' => 'nullable|string|max:255',
            'tournament_limit' => 'required|integer|min:-1',
            'members_per_group' => 'required|integer|min:-1',
            'predictions_per_day' => 'required|integer|min:-1',
            'external_leagues' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $plan = Plan::create($validated);

        return response()->json(['data' => $plan], 201);
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'monthly_price_usd' => 'sometimes|nullable|numeric|min:0',
            'annual_price_usd' => 'sometimes|nullable|numeric|min:0',
            'monthly_price_bob' => 'sometimes|nullable|numeric|min:0',
            'annual_price_bob' => 'sometimes|nullable|numeric|min:0',
            'stripe_price_id_monthly' => 'sometimes|nullable|string|max:255',
            'stripe_price_id_annual' => 'sometimes|nullable|string|max:255',
            'tournament_limit' => 'sometimes|integer|min:-1',
            'members_per_group' => 'sometimes|integer|min:-1',
            'predictions_per_day' => 'sometimes|integer|min:-1',
            'external_leagues' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $plan->update($validated);

        return response()->json(['data' => $plan->fresh()]);
    }

    public function storePromotion(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'monthly_price_usd' => 'nullable|numeric|min:0',
            'annual_price_usd' => 'nullable|numeric|min:0',
            'monthly_price_bob' => 'nullable|numeric|min:0',
            'annual_price_bob' => 'nullable|numeric|min:0',
            'predictions_per_day' => 'nullable|integer|min:-1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
        ]);

        $promotion = $plan->promotions()->create($validated);

        return response()->json(['data' => $promotion], 201);
    }

    public function updatePromotion(Request $request, Plan $plan, PlanPromotion $promotion)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'monthly_price_usd' => 'sometimes|nullable|numeric|min:0',
            'annual_price_usd' => 'sometimes|nullable|numeric|min:0',
            'monthly_price_bob' => 'sometimes|nullable|numeric|min:0',
            'annual_price_bob' => 'sometimes|nullable|numeric|min:0',
            'predictions_per_day' => 'sometimes|nullable|integer|min:-1',
            'starts_at' => 'sometimes|nullable|date',
            'ends_at' => 'sometimes|nullable|date',
            'is_active' => 'sometimes|boolean',
        ]);

        $promotion->update($validated);

        return response()->json(['data' => $promotion->fresh()]);
    }
}
