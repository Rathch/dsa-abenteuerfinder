<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adventures', function (Blueprint $table): void {
            if (! Schema::hasColumn('adventures', 'missing_from_wiki')) {
                $table->boolean('missing_from_wiki')->default(false)->after('incomplete');
                $table->index('missing_from_wiki');
            }
        });
    }

    public function down(): void
    {
        Schema::table('adventures', function (Blueprint $table): void {
            if (Schema::hasColumn('adventures', 'missing_from_wiki')) {
                $table->dropIndex(['missing_from_wiki']);
                $table->dropColumn('missing_from_wiki');
            }
        });
    }
};
