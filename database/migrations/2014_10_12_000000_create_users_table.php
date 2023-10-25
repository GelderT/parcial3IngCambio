<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('last_name', 100)->nullable(); // Permite que last_name sea nulo
            $table->string('name', 100);
            $table->string('email', 100)->unique();
            $table->string('user', 50)->unique();
            $table->string('password', 60);
            $table->enum('type', ['user', 'admin'])->nullable(); // Permite que type sea nulo
            $table->boolean('active')->nullable(); // Permite que active sea nulo
            $table->text('address')->nullable(); // Permite que address sea nulo
            $table->rememberToken();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
