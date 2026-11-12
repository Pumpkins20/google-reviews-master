<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPhoneDetailsToPhotoAnalysesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('photo_analyses', function (Blueprint $table) {
            $table->text('phone_details')->nullable()->after('official_phones');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('photo_analyses', function (Blueprint $table) {
            $table->dropColumn('phone_details');
        });
    }
}
