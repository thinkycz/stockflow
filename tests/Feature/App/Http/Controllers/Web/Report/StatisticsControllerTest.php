<?php

declare(strict_types=1);

\test('guest is redirected from legacy statistics to login', function (): void {
    $this->get('/reports/statistics')->assertRedirect('/login');
});

\test('legacy statistics redirects an administrator to unified reports', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $this->be($user, 'users')->get('/reports/statistics')->assertRedirect('/reports');
});
