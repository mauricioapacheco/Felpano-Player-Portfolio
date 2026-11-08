<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Carbon;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => [
                'user' => $request->user(),
            ],

            // ✅ PLANO (PAYWALL)
            'plan' => function () use ($request) {
                $user = $request->user();

                // visitante
                if (!$user) {
                    return [
                        'active' => false,
                        'status' => null,
                        'ends_at' => null,
                    ];
                }

                // pega plano atual (relacionamento no User: currentPlan())
                $plan = $user->currentPlan;

                // calcula ativo
                $active = false;
                if ($plan && $plan->status === 'active' && $plan->ends_at) {
                    $active = Carbon::parse($plan->ends_at)->isFuture();
                }

                return [
                    'active' => (bool) $active,
                    'status' => $plan?->status,
                    'ends_at' => $plan?->ends_at?->toIso8601String(),
                ];
            },
        ];
    }
}