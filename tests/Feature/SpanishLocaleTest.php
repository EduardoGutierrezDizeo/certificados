<?php

use App\Models\User;
use Tests\Concerns\RefreshDatabaseWithRoles;

uses(RefreshDatabaseWithRoles::class);

test('invalid login credentials show Spanish error message', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $response->assertSessionHas('errors', function ($errors) {
        return $errors->first('email') === 'Las credenciales proporcionadas no coinciden con nuestros registros.';
    });
});

test('missing registration field shows Spanish validation error', function () {
    $response = $this->post('/register', [
        'name' => '',
        'email' => '',
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'password']);
    $response->assertSessionHas('errors', function ($errors) {
        return str_contains($errors->first('name'), 'obligatorio')
            && str_contains($errors->first('email'), 'obligatorio')
            && str_contains($errors->first('password'), 'obligatorio');
    });
});

test('invalid email format shows Spanish validation error', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'not-an-email',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms_accepted' => '1',
    ]);

    $response->assertSessionHasErrors('email');
    $response->assertSessionHas('errors', function ($errors) {
        return str_contains($errors->first('email'), 'correo electrónico válida');
    });
});

test('password reset link sent shows Spanish status message', function () {
    $user = User::factory()->create();

    $response = $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    $response->assertSessionHas('status');
    expect($response->getSession()->get('status'))->toBe(
        'Hemos enviado por correo electrónico el enlace para restablecer tu contraseña.'
    );
});

test('nonexistent email shows Spanish user not found message', function () {
    $response = $this->post('/forgot-password', [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertSessionHasErrors('email');
    $response->assertSessionHas('errors', function ($errors) {
        return str_contains($errors->first('email'), 'No encontramos un usuario');
    });
});
