<?php

// SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-only

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adventure_corrections', function (Blueprint $table): void {
            $table->id();
            $table->string('adventure_slug')->unique();
            $table->json('fields');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adventure_corrections');
    }
};
