<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('auth.password');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed',
                'different:password_lama'],
        ], [
            'password_lama.current_password' => 'Kata sandi lama salah.',
            'password.different' => 'Kata sandi baru harus berbeda dari yang lama.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
        ]);

        // Cast "hashed" pada model User otomatis meng-hash, jangan pakai Hash::make()
        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Kata sandi berhasil diubah.');
    }
}