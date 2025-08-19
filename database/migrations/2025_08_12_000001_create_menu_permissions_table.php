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
        Schema::create('menu_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->onDelete('cascade')->comment('menus.id 참조');
            $table->string('type', 10)->comment('admin, level, group');
            $table->unsignedTinyInteger('subject_id')->comment('권한 주체 id: member level(1-255), groups.id, admin id 등');
            $table->unsignedTinyInteger('permission')->default(0)->comment('bitmask 권한');
            $table->timestamps();
            $table->index(['menu_id', 'type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_permissions');
    }
};
