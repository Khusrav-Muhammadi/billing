<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->string('start_date')->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->dropColumn('start_date');
        });
    }
};

