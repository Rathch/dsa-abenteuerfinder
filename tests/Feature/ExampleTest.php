<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('ELF (Ulisses)', false)
            ->assertSee('https://elf.ulisses-spiele.de/', false)
            ->assertSee('GNU GPLv3', false)
            ->assertSee('https://www.gnu.org/licenses/gpl-3.0.html', false)
            ->assertSee('Buy Me a Coffee', false)
            ->assertSee('https://buymeacoffee.com/rathch', false)
            ->assertSee('Fehler melden', false)
            ->assertSee('https://github.com/Rathch/dsa-abenteuerfinder/issues/new', false)
            ->assertSee('images/fan-produkt-logo.png', false)
            ->assertSee('Significant Fantasy Medienrechte GbR', false);
    }
}
