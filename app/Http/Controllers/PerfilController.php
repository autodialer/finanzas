<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PerfilController extends Controller
{
    public function editPassword()
    {
        return view('perfil.cambiar-password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'password_actual'   => ['required'],
            'password'          => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'password_actual.required' => 'Debes ingresar tu contraseña actual.',
            'password.required'        => 'La nueva contraseña es obligatoria.',
            'password.confirmed'       => 'Las contraseñas no coinciden.',
            'password.min'             => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->password_actual, $user->password)) {
            return back()->withErrors(['password_actual' => 'La contraseña actual no es correcta.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('exito', 'Contraseña actualizada correctamente.');
    }
}
