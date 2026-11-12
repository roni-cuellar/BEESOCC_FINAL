<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function registro(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:72|confirmed',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'email' => 'El correo electrónico no es válido.',
            'unique' => 'El correo electrónico ya está registrado.',
            'confirmed' => 'Las contraseñas no coinciden.',
            'min' => 'El campo :attribute debe tener al menos :min caracteres.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
        ]);

        $usuario = User::create($datos);
        Auth::guard('web')->login($usuario);
        $request->session()->regenerate();

        return response()->json(['message' => 'Cuenta creada correctamente.', 'usuario' => $usuario], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'email' => 'El correo electrónico no es válido.',
        ]);

        if (! Auth::guard('web')->attempt($datos)) {
            throw ValidationException::withMessages([
                'email' => ['El correo o la contraseña son incorrectos.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['message' => 'Sesión iniciada correctamente.', 'usuario' => Auth::guard('web')->user()]);
    }

    public function usuario(Request $request): JsonResponse
    {
        return response()->json(['usuario' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}
