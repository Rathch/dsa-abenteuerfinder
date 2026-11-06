<?php

// SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-only

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Manuelle Korrekturen an einem Abenteuer, die nach dem JSON-Import wiederhergestellt werden.
 *
 * @property int $id
 * @property string $adventure_slug
 * @property array<string, mixed> $fields
 */
class AdventureCorrection extends Model
{
    protected $fillable = [
        'adventure_slug',
        'fields',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields' => 'array',
        ];
    }
}
