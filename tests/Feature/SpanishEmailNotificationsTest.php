<?php

use App\Models\User;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

test('registration sends Spanish verification email', function () {
    Notification::fake();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms_accepted' => '1',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    Notification::assertSentTo(
        User::where('email', 'test@example.com')->first(),
        VerifyEmail::class
    );
});

test('verification email contains Spanish content and valid link', function () {
    Notification::fake();

    $user = User::factory()->create(['email_verified_at' => null]);

    event(new Registered($user));

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user) {
        $mail = $notification->toMail($user);

        return str_contains($mail->subject, 'Verifica tu correo electrónico')
            && str_contains($mail->render(), 'Haz clic en el botón de abajo para confirmar tu dirección de correo electrónico y activar tu cuenta en CertiCheck')
            && str_contains($mail->render(), 'Verificar correo electrónico')
            && str_contains($mail->render(), 'Si no creó esta cuenta, puede ignorar este mensaje')
            && str_contains($mail->render(), '¡Hola!');
    });
});

test('password reset sends Spanish email with correct link', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    $response->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $mail = $notification->toMail($user);
        $rendered = $mail->render();

        return str_contains($mail->subject, 'Restablece tu contraseña')
            && str_contains($rendered, 'Recibimos una solicitud para restablecer la contraseña de tu cuenta en CertiCheck')
            && str_contains($rendered, 'Restablecer contraseña')
            && str_contains($rendered, 'Este enlace expirará en')
            && str_contains($rendered, 'minutos')
            && str_contains($rendered, 'Si no solicitó el restablecimiento de contraseña, puede ignorar este mensaje')
            && str_contains($rendered, '¡Hola!');
    });
});

test('password reset email contains functional reset link', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $mail = $notification->toMail($user);
        $rendered = $mail->render();

        return str_contains($rendered, '/reset-password/')
            && str_contains($rendered, 'email=');
    });
});
