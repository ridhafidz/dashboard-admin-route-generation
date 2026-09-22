<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function getTitle(): string | Htmlable
    {
        return 'Manohara Adika Distrindo';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Silakan masuk untuk melanjutkan';
    }

    public function authenticate(): ?LoginResponse
    {
        $data     = $this->form->getState();
        $email    = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // 1. Email tidak terdaftar
        $user = User::where('email', $email)->first();

        if (! $user) {
            Notification::make()
                ->title('Email Tidak Ditemukan')
                ->body('Akun dengan email ini belum terdaftar. Hubungi administrator untuk melakukan registrasi.')
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'data.email' => 'Email tidak ditemukan. Hubungi administrator untuk registrasi.',
            ]);
        }

        // 2. Password salah
        if (! Hash::check($password, $user->password)) {
            Notification::make()
                ->title('Password Salah')
                ->body('Password yang Anda masukkan tidak sesuai. Silakan coba lagi.')
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'data.email' => 'Password yang Anda masukkan salah.',
            ]);
        }

        // 3. Role driver — tidak boleh login di panel ini
        if ($user->hasRole('driver')) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body('Driver tidak dapat login melalui halaman ini. Gunakan aplikasi mobile Driver.')
                ->warning()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'data.email' => 'Driver tidak memiliki akses ke panel ini.',
            ]);
        }

        // 4. Tidak memiliki role apapun / role lain yang tidak diizinkan
        if (! $user->hasAnyRole(['admin', 'ops'])) {
            Notification::make()
                ->title('Tidak Memiliki Izin')
                ->body('Akun Anda tidak memiliki izin untuk mengakses panel ini. Hubungi administrator.')
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'data.email' => 'Akun Anda tidak memiliki izin untuk mengakses panel ini.',
            ]);
        }

        // 5. Semua validasi lolos → proses login normal (rate limiting, events, dll)
        return parent::authenticate();
    }
}


