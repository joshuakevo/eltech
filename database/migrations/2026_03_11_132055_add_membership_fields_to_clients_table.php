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
            // Contact extras
            $table->string('alt_phone')->nullable()->after('phone');
            $table->string('district')->nullable()->after('address');
            $table->string('village')->nullable()->after('district');
            $table->string('postal_address')->nullable()->after('village');

            // Employment & membership
            $table->enum('employment_status', ['employed','self_employed','business_owner','farmer','student','unemployed'])->nullable()->after('postal_address');
            $table->string('purpose_of_joining')->nullable()->after('employment_status');
            $table->decimal('expected_monthly_savings', 12, 2)->nullable()->after('purpose_of_joining');
            $table->boolean('loan_interest')->nullable()->after('expected_monthly_savings');

            // Next of kin
            $table->string('next_of_kin_name')->nullable()->after('loan_interest');
            $table->string('next_of_kin_relationship')->nullable()->after('next_of_kin_name');
            $table->string('next_of_kin_phone')->nullable()->after('next_of_kin_relationship');
            $table->string('next_of_kin_address')->nullable()->after('next_of_kin_phone');

            // Preferences & metadata
            $table->enum('preferred_communication', ['sms','email','whatsapp','phone_call'])->nullable()->after('next_of_kin_address');
            $table->unsignedBigInteger('created_by')->nullable()->after('preferred_communication');
        });
    }

    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'alt_phone', 'district', 'village', 'postal_address',
                'employment_status', 'purpose_of_joining', 'expected_monthly_savings', 'loan_interest',
                'next_of_kin_name', 'next_of_kin_relationship', 'next_of_kin_phone', 'next_of_kin_address',
                'preferred_communication', 'created_by',
            ]);
        });
    }
};
