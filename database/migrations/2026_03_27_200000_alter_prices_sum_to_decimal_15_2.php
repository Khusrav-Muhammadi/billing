<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `prices` MODIFY `sum` DECIMAL(15,2) NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE prices ALTER COLUMN sum TYPE NUMERIC(15,2)');
            return;
        }

        // sqlite: keep as-is (Laravel uses NUMERIC under the hood for decimal).
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `prices` MODIFY `sum` DECIMAL(8,2) NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE prices ALTER COLUMN sum TYPE NUMERIC(8,2)');
            return;
        }
    }
};

