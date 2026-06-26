<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Covers the visualData query: WHERE status = ? ORDER BY created_time
            // and the date-range filter: WHERE status = ? AND created_time BETWEEN ? AND ?
            $table->index(['status', 'created_time'], 'events_status_created_time_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_status_created_time_index');
        });
    }
};
