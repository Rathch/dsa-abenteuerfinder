<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adventures', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->foreignId('series_id')->constrained('series')->restrictOnDelete();
            $table->enum('experience_level', ['einsteiger', 'fortgeschritten', 'erfahren', 'meister']);
            $table->unsignedInteger('page_count')->nullable();
            $table->enum('duration_category', ['kurz', 'mittel', 'lang', 'kampagne']);
            $table->enum('rule_version', ['dsa4', 'dsa5', 'editionunabhaengig']);
            $table->boolean('exotic')->default(false);
            $table->enum('complexity', ['niedrig', 'mittel', 'hoch']);
            $table->unsignedSmallInteger('group_size_min')->nullable();
            $table->unsignedSmallInteger('group_size_max')->nullable();
            $table->enum('location_type', ['point', 'corridor', 'traveling']);
            $table->foreignId('primary_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('primary_region_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->text('short_description');
            $table->string('wiki_url');
            $table->boolean('incomplete')->default(false);
            $table->boolean('missing_from_wiki')->default(false);
            $table->string('source_note')->nullable();
            $table->timestamps();

            $table->index('incomplete');
            $table->index('missing_from_wiki');
        });

        Schema::create('adventure_genre_tag', function (Blueprint $table): void {
            $table->foreignId('adventure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genre_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['adventure_id', 'genre_tag_id']);
        });

        Schema::create('adventure_region', function (Blueprint $table): void {
            $table->foreignId('adventure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('locations')->cascadeOnDelete();
            $table->primary(['adventure_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adventure_region');
        Schema::dropIfExists('adventure_genre_tag');
        Schema::dropIfExists('adventures');
    }
};
