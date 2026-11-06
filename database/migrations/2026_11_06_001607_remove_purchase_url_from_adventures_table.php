<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('adventures', 'purchase_url')) {
            Schema::table('adventures', function (Blueprint $table): void {
                $table->dropColumn('purchase_url');
            });
        }

        if (! Schema::hasTable('adventure_corrections')) {
            return;
        }

        foreach (DB::table('adventure_corrections')->cursor() as $row) {
            $fields = json_decode((string) $row->fields, true);
            if (! is_array($fields) || ! array_key_exists('purchase_url', $fields)) {
                continue;
            }

            unset($fields['purchase_url']);

            if ($fields === []) {
                DB::table('adventure_corrections')->where('id', $row->id)->delete();

                continue;
            }

            DB::table('adventure_corrections')->where('id', $row->id)->update([
                'fields' => json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('adventures', 'purchase_url')) {
            Schema::table('adventures', function (Blueprint $table): void {
                $table->string('purchase_url')->nullable();
            });
        }
    }
};
