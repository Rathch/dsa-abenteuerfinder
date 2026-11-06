<?php

// SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-only

namespace App\Livewire\Admin;

use App\Services\AdventureCorrectionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AdventureIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'link', history: true)]
    public string $linkFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingLinkFilter(): void
    {
        $this->resetPage();
    }

    public function render(AdventureCorrectionService $corrections): View
    {
        return view('livewire.admin.adventure-index', [
            'adventures' => $corrections->paginate([
                'q' => $this->search,
                'link' => $this->linkFilter,
            ]),
        ])->layout('layouts.admin', [
            'title' => 'Abenteuer verwalten',
        ]);
    }
}
