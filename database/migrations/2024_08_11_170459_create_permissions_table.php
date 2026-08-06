<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionsTable extends Migration
{
    public function up()
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->string('type')->nullable(); // e.g., 'full', 'write', 'read'
            $table->string('module')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable(); // For hierarchical structure
            $table->foreign('parent_id')->references('id')->on('permissions')->onDelete('cascade'); // Change to string
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('permissions');
    }
}
