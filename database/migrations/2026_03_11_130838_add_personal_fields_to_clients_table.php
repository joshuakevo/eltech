<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('client_number');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('last_name');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable()->after('date_of_birth');
            $table->string('nationality')->nullable()->after('marital_status');
            $table->string('id_number')->nullable()->after('nationality');
            $table->string('photo')->nullable()->after('id_number');
        });
    }

    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'middle_name', 'last_name', 'gender',
                'date_of_birth', 'marital_status', 'nationality', 'id_number', 'photo',
            ]);
        });
    }
};
