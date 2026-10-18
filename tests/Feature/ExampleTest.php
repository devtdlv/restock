<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Guests hitting the root URL are redirected into the auth flow.
     */
    public function test_the_application_redirects_guests_from_the_home_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');
    }
}
