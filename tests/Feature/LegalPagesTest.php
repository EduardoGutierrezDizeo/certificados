<?php

it('shows terms and conditions page publicly', function () {
    $response = $this->get(route('legal.terms'));

    $response->assertOk();
    $response->assertSee('Términos y Condiciones de Uso');
    $response->assertSee('Versión '.config('legal.terms_version'));
    $response->assertSee(config('legal.terms_updated_at'));
    $response->assertSee('Eduardo José Gutierrez De Piñerez Dizeo');
});

it('shows privacy policy page publicly', function () {
    $response = $this->get(route('legal.privacy'));

    $response->assertOk();
    $response->assertSee('Política de Tratamiento de Datos Personales');
    $response->assertSee('Versión '.config('legal.terms_version'));
    $response->assertSee(config('legal.terms_updated_at'));
    $response->assertSee('Ley 1581 de 2012');
});

it('does not require authentication for legal pages', function () {
    $this->get(route('legal.terms'))->assertOk();
    $this->get(route('legal.privacy'))->assertOk();
});
