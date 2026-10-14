<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_member_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_member_id')->constrained('event_members')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_member_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_member_permissions');
    }
};
