<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE fixed_deposits MODIFY COLUMN status ENUM('active', 'matured', 'closed', 'broken') NOT NULL DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE fixed_deposits MODIFY COLUMN status ENUM('active', 'matured', 'closed') NOT NULL DEFAULT 'active'");
    }
};
