<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adventures', function (Blueprint $table): void {
            if (! Schema::hasColumn('adventures', 'klappentext')) {
                $table->text('klappentext')->nullable()->after('short_description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('adventures', function (Blueprint $table): void {
            if (Schema::hasColumn('adventures', 'klappentext')) {
                $table->dropColumn('klappentext');
            }
        });
    }
};
