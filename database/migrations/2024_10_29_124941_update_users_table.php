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
        Schema::table('users', function (Blueprint $table) {
            $table->string('hashslug', 20)->unique()->after('id'); 

            $table->string('provider', 100)->nullable();
            $table->string('provider_id', 250)->nullable();
            $table->string('qrcode', 250)->nullable();

            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('icno', 20)->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('dob')->nullable();
            $table->string('race', 50)->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('phone_no', 50)->nullable();

            $table->unsignedInteger('rider_id')->index()->nullable();
            $table->unsignedInteger('merchant_id')->index()->nullable();

            $table->text('address_line_1')->nullable();
            $table->text('address_line_2')->nullable();
            $table->text('address_line_3')->nullable();
            $table->string('postcode', 100)->nullable();   
            $table->string('city', 100)->nullable();                     
            $table->unsignedInteger('state_id')->index()->nullable();
            $table->unsignedInteger('country_id')->index()->nullable();

            $table->string('language', 50)->nullable();
            $table->text('detail')->nullable();
            $table->string('avatar', 255)->nullable();
            $table->decimal('latitude', 18,5)->nullable();
            $table->decimal('longitude', 18,5)->nullable();

            $table->boolean('is_active')->default(0);
            $table->string('status', 50)->nullable();            

            $table->boolean('otp_verified')->default(0);
            $table->dateTime('otp_verified_date')->nullable();

            $table->timestamp('last_login_date')->nullable();
            $table->string('last_login_ip', 50)->nullable();

            $table->unsignedInteger('created_by')->index()->nullable();
            $table->unsignedInteger('updated_by')->index()->nullable();
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
