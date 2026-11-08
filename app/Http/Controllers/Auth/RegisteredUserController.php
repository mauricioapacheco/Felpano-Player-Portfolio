<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Normaliza (remove máscara e deixa somente números)
        $cpf = preg_replace('/\D+/', '', (string) $request->input('cpf'));
        $telefone = preg_replace('/\D+/', '', (string) $request->input('telefone'));

        // Joga os valores normalizados de volta no request para validar/salvar
        $request->merge([
            'cpf' => $cpf,
            'telefone' => $telefone,
        ]);

        $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
                'telefone' => ['required', 'string', 'min:10', 'max:20', Rule::unique('users', 'telefone')],
                'cpf' => ['required', 'string', 'size:11', Rule::unique('users', 'cpf')],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ],
            [
                'telefone.required' => 'Informe o telefone.',
                'telefone.unique' => 'Este telefone já está cadastrado.',
                'telefone.min' => 'O telefone deve ter pelo menos 10 dígitos.',
                'telefone.max' => 'O telefone deve ter no máximo 20 dígitos.',

                'cpf.required' => 'Informe o CPF.',
                'cpf.size' => 'O CPF deve conter exatamente 11 dígitos.',
                'cpf.unique' => 'Este CPF já está cadastrado.',
            ]
        );

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'telefone' => $request->input('telefone'),
            'cpf' => $request->input('cpf'),
            'password' => Hash::make($request->input('password')),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}