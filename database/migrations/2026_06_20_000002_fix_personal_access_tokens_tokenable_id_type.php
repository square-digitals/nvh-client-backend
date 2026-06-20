<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $type = DB::selectOne(
            "SELECT data_type FROM information_schema.columns
             WHERE table_name = 'personal_access_tokens' AND column_name = 'tokenable_id'"
        )?->data_type;

        if ($type === 'character varying') {
            return;
        }

        DB::table('personal_access_tokens')->truncate();

        DB::statement(
            'ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE varchar(255) USING tokenable_id::varchar'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE bigint USING tokenable_id::bigint'
        );
    }
};
