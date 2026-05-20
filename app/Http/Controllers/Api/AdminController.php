<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Invoice;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_users'    => User::count(),
                'active_users'   => User::where('is_active', true)->count(),
                'inactive_users' => User::where('is_active', false)->count(),
                'free_users'     => Workspace::where('plan', 'free')->count(),
                'pro_users'      => Workspace::where('plan', 'pro')->count(),
                'business_users' => Workspace::where('plan', 'business')->count(),
                'total_invoices' => Invoice::count(),
                'paid_invoices'  => Invoice::where('status', 'paid')->count(),
                'mrr'            => (Workspace::where('plan', 'pro')->count() * 10000)
                                  + (Workspace::where('plan', 'business')->count() * 20000),
            ],
        ]);
    }

    public function users(Request $request)
    {
        $query = User::with('workspace')->orderBy('created_at', 'desc');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        if ($request->plan) {
            $query->whereHas('workspace', fn($q) => $q->where('plan', $request->plan));
        }

        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate(25),
        ]);
    }

    public function updatePlan(Request $request, User $user)
    {
        $validated = $request->validate([
            'plan' => 'required|in:free,pro,business',
        ]);

        $user->workspace?->update(['plan' => $validated['plan']]);

        return response()->json([
            'success' => true,
            'message' => "Plan updated to {$validated['plan']}",
            'data'    => $user->fresh('workspace'),
        ]);
    }

    public function toggleActive(User $user)
    {
        if ($user->is_admin) {
            return response()->json(['success' => false, 'message' => 'Cannot deactivate an admin'], 422);
        }

        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "User {$status}",
            'data'    => $user->fresh('workspace'),
        ]);
    }
}
