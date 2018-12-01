<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Connectors extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('connectors', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('scopes')->nullable();
            $table->boolean('signup')->default(1);
            $table->boolean('login')->default(1);

            $table->unique('name');
            $table->timestamps();
        });
        Schema::create('connector_user', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('connector_id');
            $table->string('id')->nullable();
            $table->string('email')->nullable();
            $table->string('scopes')->nullable();
            $table->string('access_token')->nullable();
            $table->string('refresh_token')->nullable();
            $table->unsignedBigInteger('expires_in')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('connector_id')->references('id')->on('connectors')->onDelete('cascade')->onUpdate('cascade');
            $table->index('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('connector_user');
        Schema::dropIfExists('connectors');
    }
}
