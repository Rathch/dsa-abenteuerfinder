<?php

namespace App\View\Components;

use App\Services\CatalogStatsService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class CatalogStats extends Component
{
    public function __construct(private readonly CatalogStatsService $stats) {}

    public function render(): View|Closure|string
    {
        return view('components.catalog-stats', $this->stats->overview());
    }
}
