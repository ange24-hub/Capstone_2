<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'current_password' => [Rule::requiredIf($request->filled('password') || $request->input('email') !== $user->email), 'nullable', 'current_password'],
        ]);
        $user->name = $data['name'];
        if ($user->email !== $data['email']) $user->email_verified_at = null;
        $user->email = $data['email'];
        if (! empty($data['password'])) $user->password = $data['password'];
        $user->save();
        return redirect()->route('profile.edit')->with('status', 'Your profile has been updated.');
    }
}
