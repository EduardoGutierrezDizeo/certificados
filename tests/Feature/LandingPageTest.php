<?php

it('shows the contact section with phone and email on the landing page', function () {
    $response = $this->get(route('landing'));

    $response->assertOk();
    $response->assertSee('Contáctanos');
    $response->assertSee('+57 3135997282');
    $response->assertSee('certicheck@certicheck.site');
});

it('links the contact details with tel and mailto protocols', function () {
    $response = $this->get(route('landing'));

    $response->assertOk();
    $response->assertSee('href="tel:+573135997282"', false);
    $response->assertSee('href="mailto:certicheck@certicheck.site"', false);
});
