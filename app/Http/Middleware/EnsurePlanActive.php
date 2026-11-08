<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EnsurePlanActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return redirect('/');
        }

        $plan = $user->currentPlan;

        $active = $plan
            && $plan->status === 'active'
            && $plan->ends_at
            && Carbon::parse($plan->ends_at)->isFuture();

        if ($active) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Plano inativo. Realize o pagamento.'
            ], 402);
        }

        return redirect('/');
    }
}