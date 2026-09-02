<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'E-mail ou senha invalidos.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('app.hub'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:180'],
            'trade_name' => ['nullable', 'string', 'max:180'],
            'document' => ['required', 'string', 'max:32', 'unique:customers,document'],
            'company_email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = DB::transaction(function () use ($data) {
            $customer = Customer::query()->create([
                'legal_name' => $data['legal_name'],
                'trade_name' => $data['trade_name'] ?: $data['legal_name'],
                'document' => $data['document'],
                'email' => $data['company_email'] ?: $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => 'active',
            ]);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'customer_id' => $customer->id,
                'status' => 'active',
            ]);

            $plan = Plan::query()->where('slug', 'gratuito')->where('is_active', true)->first();

            if ($plan) {
                Subscription::query()->create([
                    'customer_id' => $customer->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'activated_at' => now(),
                    'metadata' => [
                        'source' => 'self_registration',
                        'product' => 'cultura',
                    ],
                ]);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('cultura.profile.edit')
            ->with('status', 'Cliente criado com sucesso. Complete o Perfil Cultural para personalizar o Radar.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
