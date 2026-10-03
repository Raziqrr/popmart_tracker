<?php

// The home page is now the Inertia dashboard, which needs the MySQL schema;
// the health route proves the app boots without one.
it('returns a successful response', function () {
    $response = $this->get('/up');

    $response->assertStatus(200);
});
