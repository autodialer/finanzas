<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'El correo es obligatorio.',
            'email.email'    => 'Ingresa un correo electrónico válido.',
        ]);

        // No revelamos si el correo existe o no (seguridad)
        return back()->with(
            'exito',
            'Solicitud registrada. Contacta al administrador del sistema para obtener tu enlace de recuperación.'
        );
    }
}
