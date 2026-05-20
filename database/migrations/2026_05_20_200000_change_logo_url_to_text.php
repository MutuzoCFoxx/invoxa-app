<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Change logo_url from varchar to text to hold base64 data URIs
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE workspaces ALTER COLUMN logo_url TYPE TEXT');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE workspaces MODIFY logo_url LONGTEXT NULL');
        }
        // SQLite allows any length in varchar, no change needed
    }

    public function down(): void
    {
        // Cannot safely downsize — skip
    }
};
