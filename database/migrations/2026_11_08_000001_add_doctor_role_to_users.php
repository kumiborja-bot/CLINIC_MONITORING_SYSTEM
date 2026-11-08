<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'nurse', 'doctor') NOT NULL DEFAULT 'nurse'");
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET role = 'nurse' WHERE role = 'doctor'");
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'nurse') NOT NULL DEFAULT 'nurse'");
    }
};
