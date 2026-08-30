<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['nullable', 'required_without:email', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:login', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $login = $validated['login'] ?? $validated['email'];
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'staff_id';

        if (! Auth::attempt([$field => $login, 'password' => $validated['password']], $request->boolean('remember'))) {
            return back()
                ->withErrors(['login' => 'The provided email or user ID does not match our records.'])
                ->withInput(['login' => $login]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        return view('auth.register', [
            'roles' => [
                User::ROLE_RESIDENT => User::roleLabels()[User::ROLE_RESIDENT],
                User::ROLE_BARANGAY => User::roleLabels()[User::ROLE_BARANGAY],
            ],
            'barangays' => Barangay::where('municipality', Barangay::MUNICIPALITY)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => Str::lower(trim((string) $request->input('email'))),
            'user_id' => Str::upper(trim((string) $request->input('user_id'))),
        ]);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'user_id' => ['nullable', 'required_if:role,'.User::ROLE_BARANGAY, 'string', 'max:100', 'alpha_dash', 'unique:users,staff_id'],
            'role' => ['required', 'in:resident,barangay'],
            'barangay_id' => ['required', 'exists:barangays,id'],
            'access_code' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $role = (string) $request->input('role');

            if ($role !== User::ROLE_BARANGAY) {
                return;
            }

            $configuredCode = config("auth.registration_codes.{$role}");

            if (! $configuredCode || ! hash_equals($configuredCode, (string) $request->input('access_code'))) {
                $validator->errors()->add('access_code', 'A valid LGU access code is required for this role.');
            }
        });

        $validated = $validator->validate();

        $user = DB::transaction(fn (): User => User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'staff_id' => $validated['role'] === User::ROLE_BARANGAY
                ? $validated['user_id']
                : null,
            'role' => $validated['role'],
            'barangay_id' => $validated['barangay_id'],
            'approval_status' => User::APPROVAL_PENDING,
            'approved_at' => null,
            'password' => Hash::make($validated['password']),
        ]));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function pendingApproval(Request $request): View
    {
        return view('auth.pending-approval', [
            'resident' => $request->user()->load('barangay'),
        ]);
    }
}
