<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Panel de soporte para super admin (17/10/2026) — gestión de OTROS
 * usuarios, separado de UserController que es exclusivamente self-service
 * (mismo criterio que PagoAdicionalController separado de
 * RegistrationController en el proyecto hermano, para no mezclar "mi
 * perfil" con "el perfil de cualquiera"). Todas las rutas van detrás del
 * middleware 'admin' ya existente.
 */
class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($email = $request->query('email')) {
            $query->where('email', 'like', "%{$email}%");
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        return response()->json($query->orderBy('user_id', 'desc')->paginate());
    }

    public function show(User $user)
    {
        $user->load(['subscription.payments']);

        return response()->json(['data' => $user]);
    }

    /**
     * Override manual de soporte (ej. "le extiendo el plan por las
     * molestias") — nunca toca email/password acá, eso es
     * resetPassword() explícito más abajo.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'sometimes|nullable|string',
            // Planes administrables (20/10/2026) — acepta cualquier slug que
            // exista en `plans`, no solo los 4 originales hardcodeados.
            'subscription_tier' => ['sometimes', Rule::in(Plan::pluck('slug'))],
            'subscription_expires_at' => 'sometimes|nullable|date',
        ]);

        $user->update($validated);

        return response()->json(['data' => $user->fresh()]);
    }

    /**
     * Genera una contraseña temporal y revoca las sesiones activas del
     * usuario — el admin se la pasa por el canal de soporte que use
     * (WhatsApp/chat), no se manda por correo en texto plano.
     */
    public function resetPassword(User $user)
    {
        $temporaryPassword = Str::random(10);

        $user->update(['password_hash' => Hash::make($temporaryPassword)]);
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Contraseña reseteada. Comunicala al usuario por el canal de soporte.',
            'temporary_password' => $temporaryPassword,
        ]);
    }

    /**
     * Alterna suspended_at. Al suspender, también revoca los tokens
     * activos para cortar sesiones ya abiertas, no solo bloquear logins
     * futuros (ver AuthController::login()).
     */
    public function toggleSuspended(User $user)
    {
        if ($user->suspended_at) {
            $user->update(['suspended_at' => null]);
        } else {
            $user->update(['suspended_at' => now()]);
            $user->tokens()->delete();
        }

        return response()->json(['data' => $user->fresh()]);
    }
}
