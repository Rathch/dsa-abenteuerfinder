<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adventures', function (Blueprint $table): void {
            $table->enum('rule_version', ['dsa1', 'dsa2', 'dsa3', 'dsa4', 'dsa5', 'editionunabhaengig'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('adventures', function (Blueprint $table): void {
            $table->enum('rule_version', ['dsa4', 'dsa5', 'editionunabhaengig'])->change();
        });
    }
};
