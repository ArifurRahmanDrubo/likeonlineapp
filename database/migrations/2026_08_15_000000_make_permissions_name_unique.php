<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The permissions table previously re-created permission rows on every
     * save, so the same name can exist multiple times. Before adding the
     * unique index we merge duplicates: pivot rows and child permissions are
     * re-pointed at the kept row, then the extra rows are deleted.
     */
    public function up()
    {
        $duplicateNames = DB::table('permissions')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicateNames as $name) {
            $rows = DB::table('permissions')->where('name', $name)->orderBy('id')->get();
            $keepId = $rows->first()->id;
            $duplicateIds = $rows->slice(1)->pluck('id');

            // Drop pivot rows that would collide with the kept permission
            $keptRoleIds = DB::table('role_has_permissions')->where('permission_id', $keepId)->pluck('role_id');
            if ($keptRoleIds->isNotEmpty()) {
                DB::table('role_has_permissions')
                    ->whereIn('permission_id', $duplicateIds)
                    ->whereIn('role_id', $keptRoleIds)
                    ->delete();
            }

            // Re-point remaining pivots at the kept permission
            DB::table('role_has_permissions')
                ->whereIn('permission_id', $duplicateIds)
                ->update(['permission_id' => $keepId]);

            // Re-parent child permissions to the kept row
            DB::table('permissions')
                ->whereIn('parent_id', $duplicateIds)
                ->update(['parent_id' => $keepId]);

            // Delete the duplicate rows
            DB::table('permissions')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('permissions', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
