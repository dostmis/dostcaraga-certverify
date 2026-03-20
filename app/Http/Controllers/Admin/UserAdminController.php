<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserAdminController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->get('status', 'pending');
        $role = (string) $request->get('role', 'all');
        if ($role !== 'all' && !in_array($role, User::roles(), true)) {
            $role = 'all';
        }

        $query = User::query()->orderByDesc('id');
        if ($status !== 'all') {
            $query->where('approval_status', $status);
        }
        if ($role !== 'all') {
            $query->where('role', $role);
        }

        $users = $query->paginate(10)->withQueryString();

        $roles = User::roles();

        return view('admin.users.index', compact('users', 'status', 'role', 'roles'));
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(User::roles())],
        ]);

        $user = User::findOrFail($id);
        $user->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'rejected_at' => null,
            'rejected_by' => null,
            'role' => $data['role'],
            'is_admin' => $data['role'] === User::ROLE_REGIONAL_DIRECTOR,
        ]);

        return back()->with('success', 'User approved successfully.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $user->update([
            'approval_status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $request->user()->id,
        ]);

        return back()->with('success', 'User rejected.');
    }
}
