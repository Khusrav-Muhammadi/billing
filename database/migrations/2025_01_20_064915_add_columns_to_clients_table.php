<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dateTime('last_activity')->nullable();
            $table->string('email');
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('client_type');
            $table->string('contact_person')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('email');
            $table->dropColumn('last_activity');
            $table->dropColumn('client_type');
            $table->dropColumn('contact_person');
        });
    }
};
