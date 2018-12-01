<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PrimaryKey extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('connector_user', function (Blueprint $table) {

            $table->renameColumn('id','api_id');
        });
        Schema::table('connector_user', function (Blueprint $table) {

            $table->increments('id');
        });
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('connector_user', function (Blueprint $table) {
            $table->dropColumn('id');
        });
        Schema::table('connector_user', function (Blueprint $table) {
            $table->renameColumn('api_id','id');
        });
    }
}
