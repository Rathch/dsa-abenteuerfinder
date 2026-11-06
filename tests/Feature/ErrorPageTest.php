<?php

declare(strict_types=1);

it('renders the branded 404 page', function (): void {
    $this->get('/seite-gibt-es-nicht')
        ->assertNotFound()
        ->assertSee('Seite nicht gefunden')
        ->assertSee('Zur Startseite')
        ->assertSee('logo-swords', false)
        ->assertSee('DSA-Abenteuerfinder');
});
