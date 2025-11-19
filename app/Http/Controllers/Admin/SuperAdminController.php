<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SuperAdminController extends Controller
{
    private function ensureSuper(Request $request): void
    {
        $role = strtolower((string) $request->user()->role);
        abort_unless(in_array($role, ['super','admin','owner']), 403, 'Only super admin can access');
    }

    public function users(Request $request): JsonResponse
    {
        $this->ensureSuper($request);
        $q = User::query()
            ->whereIn(DB::raw('LOWER(role)'), ['photographer','business'])
            ->orderBy('id')
            ->select(['id','name','email','role','active','premium_package','created_at']);
        if ($search = $request->query('q')) {
            $q->where(function($w) use ($search) {
                $w->where('name','like',"%$search%")
                  ->orWhere('email','like',"%$search%")
                  ->orWhere('role','like',"%$search%");
            });
        }
        return response()->json($q->paginate((int) $request->query('per_page', 20)));
    }

    public function setActive(Request $request, User $user): JsonResponse
    {
        $this->ensureSuper($request);
        abort_unless(in_array(strtolower((string)$user->role), ['photographer','business']), 422, 'Only photographer or business accounts');
        $data = $request->validate([
            'active' => ['required','boolean'],
        ]);
        $user->active = (bool) $data['active'];
        $user->save();
        return response()->json(['id' => $user->id, 'active' => (bool)$user->active]);
    }

    public function incomeSummary(Request $request): JsonResponse
    {
        $this->ensureSuper($request);
        $now = Carbon::now();
        $today = $now->toDateString();

        $base = Payment::query()->where('status','paid');

        $total = (clone $base)->sum('amount');
        $todayTotal = (clone $base)->whereDate(DB::raw('COALESCE(paid_at, created_at)'), $today)->sum('amount');
        $monthTotal = (clone $base)
            ->whereYear(DB::raw('COALESCE(paid_at, created_at)'), (int)$now->year)
            ->whereMonth(DB::raw('COALESCE(paid_at, created_at)'), (int)$now->month)
            ->sum('amount');

        $ownerRate = 0.30; $photographerRate = 0.70;

        return response()->json([
            'overall' => [
                'gross' => (float)$total,
                'owner_commission' => (float)($total * $ownerRate),
                'photographer_earning' => (float)($total * $photographerRate),
            ],
            'today' => [
                'gross' => (float)$todayTotal,
                'owner_commission' => (float)($todayTotal * $ownerRate),
                'photographer_earning' => (float)($todayTotal * $photographerRate),
            ],
            'month' => [
                'gross' => (float)$monthTotal,
                'owner_commission' => (float)($monthTotal * $ownerRate),
                'photographer_earning' => (float)($monthTotal * $photographerRate),
            ],
            'rates' => [ 'owner' => $ownerRate, 'photographer' => $photographerRate ],
        ]);
    }

    public function setPremium(Request $request, User $user): JsonResponse
    {
        $this->ensureSuper($request);
        abort_unless(in_array(strtolower((string)$user->role), ['photographer','business']), 422, 'Only photographer or business accounts');
        $data = $request->validate([
            'premium_package' => ['required','boolean'],
        ]);
        $user->premium_package = (bool)$data['premium_package'];
        $user->save();
        return response()->json([
            'id' => $user->id,
            'premium_package' => (bool)$user->premium_package,
        ]);
    }
}
