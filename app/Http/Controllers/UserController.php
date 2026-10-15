<?php

namespace App\Http\Controllers;

use App\Models\Negocio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UserController extends Controller
{
    public function index()
    {
        $usuarios = User::orderBy('name')->get();
        return view('usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $negocios = Negocio::orderBy('nombre')->get();
        return view('usuarios.create', compact('negocios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'role'       => ['required', 'in:admin,gerente,capturista'],
            'negocio_id' => ['required_if:role,capturista', 'nullable', 'exists:negocios,id'],
            'password'   => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'name.required'         => 'El nombre es obligatorio.',
            'email.required'        => 'El correo es obligatorio.',
            'email.unique'          => 'Este correo ya está registrado.',
            'role.required'         => 'El rol es obligatorio.',
            'negocio_id.required_if'=> 'El negocio es obligatorio para el rol capturista.',
            'password.required'     => 'La contraseña es obligatoria.',
            'password.confirmed'    => 'Las contraseñas no coinciden.',
            'password.min'          => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'role'       => $request->role,
            'negocio_id' => $request->role === 'capturista' ? $request->negocio_id : null,
            'password'   => $request->password,
        ]);

        return redirect()->route('usuarios.index')
            ->with('exito', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        $negocios = Negocio::orderBy('nombre')->get();
        return view('usuarios.edit', compact('usuario', 'negocios'));
    }

    public function update(Request $request, User $usuario)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'unique:users,email,' . $usuario->id],
            'role'       => ['required', 'in:admin,gerente,capturista'],
            'negocio_id' => ['required_if:role,capturista', 'nullable', 'exists:negocios,id'],
            'password'   => ['nullable', 'confirmed', PasswordRule::min(8)],
        ], [
            'name.required'          => 'El nombre es obligatorio.',
            'email.required'         => 'El correo es obligatorio.',
            'email.unique'           => 'Este correo ya está registrado.',
            'role.required'          => 'El rol es obligatorio.',
            'negocio_id.required_if' => 'El negocio es obligatorio para el rol capturista.',
            'password.confirmed'     => 'Las contraseñas no coinciden.',
            'password.min'           => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $data = [
            'name'       => $request->name,
            'email'      => $request->email,
            'role'       => $request->role,
            'negocio_id' => $request->role === 'capturista' ? $request->negocio_id : null,
        ];

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $usuario->update($data);

        return redirect()->route('usuarios.index')
            ->with('exito', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')
            ->with('exito', 'Usuario eliminado correctamente.');
    }

    public function generarEnlaceReset(User $usuario)
    {
        $token  = Password::createToken($usuario);
        $enlace = url(route('password.reset', [
            'token' => $token,
            'email' => $usuario->email,
        ], false));

        return back()
            ->with('enlace_reset', $enlace)
            ->with('exito', 'Enlace generado. Cópialo y envíaselo al usuario. Expira en 60 minutos.');
    }
}
