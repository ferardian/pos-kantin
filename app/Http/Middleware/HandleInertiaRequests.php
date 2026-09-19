<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\EmployeeReceivable;
use App\Models\GoodsReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
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
            'user' => $request->user(),
            'settings' => fn () => Setting::getSettings(),
            'pendingOrdersCount' => 0,
            'pendingReceivablesCount' => fn () => Auth::check() ? EmployeeReceivable::whereIn('status', ['unpaid', 'partial'])->count() : 0,
            'pendingGoodsReceiptsCount' => fn () => (Auth::check() && Auth::user()->role === 'admin') ? GoodsReceipt::where('status', 'pending')->count() : 0,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
