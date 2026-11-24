<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // enum -> string agar role 'mitra' dan 'asesor' bisa dipakai tanpa ALTER enum manual
            $table->string('role', 30)->default('user')->change();

            if (! Schema::hasColumn('users', 'nik')) {
                $table->string('nik', 32)->nullable()->after('fullname');
            }
            if (! Schema::hasColumn('users', 'no_reg_asesor')) {
                $table->string('no_reg_asesor', 64)->nullable()->after('nik');
            }
        });
    }

    public function down(): void
    {
        //
    }
};
