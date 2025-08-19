<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade')->comment('groups.id 참조');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade')->comment('members.id 참조');
            $table->string('role', 50)->nullable()->comment('그룹 내 역할(예: manager, editor, member 등)');
            $table->timestamps();
            $table->unique(['group_id', 'member_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('group_members');
    }
};
