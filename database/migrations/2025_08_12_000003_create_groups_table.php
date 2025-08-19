<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('그룹명');
            $table->string('description', 255)->nullable()->comment('설명');
            $table->boolean('active')->default(true)->comment('그룹 활성화 상태');
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
