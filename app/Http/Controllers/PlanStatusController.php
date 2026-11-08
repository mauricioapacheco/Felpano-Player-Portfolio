<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\UserPlan;

class PlanStatusController extends Controller
{
    public function check(Request $request)
    {
        $user = $request->user();

        $plan = UserPlan::where('user_id', $user->id)
            ->latest() // mesma coisa que orderByDesc('id')
            ->first();

        if (!$plan) {
            return response()->json([
                'active' => false,
                'status' => null,
                'ends_at' => null,
            ]);
        }

        // 🔒 garante que ends_at seja Carbon
        $expiresAt = $plan->ends_at
            ? Carbon::parse($plan->ends_at)
            : null;

        // ✅ se passou da data e ainda está ativo -> expira
        if ($plan->status === 'active' && $expiresAt && $expiresAt->isPast()) {
            $plan->update([
                'status' => 'expired'
            ]);
        }

        // 🔁 recarrega status atualizado
        $plan->refresh();

        return response()->json([
            'active' => $plan->status === 'active',
            'status' => $plan->status,
            'ends_at' => $plan->ends_at,
        ]);
    }
}
