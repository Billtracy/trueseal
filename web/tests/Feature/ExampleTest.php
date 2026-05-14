<?php

it('redirects guests from the landing page to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
