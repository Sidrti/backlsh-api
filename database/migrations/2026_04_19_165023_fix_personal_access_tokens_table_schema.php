<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Check if id is already primary key
            $primaryKey = DB::select("SHOW INDEX FROM personal_access_tokens WHERE Key_name = 'PRIMARY'");
            if (empty($primaryKey)) {
                DB::statement('ALTER TABLE personal_access_tokens MODIFY id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY');
            } else {
                DB::statement('ALTER TABLE personal_access_tokens MODIFY id BIGINT UNSIGNED AUTO_INCREMENT');
            }

            // Check and add unique index for token
            $tokenIndex = DB::select("SHOW INDEX FROM personal_access_tokens WHERE Column_name = 'token' AND Non_unique = 0");
            if (empty($tokenIndex)) {
                $table->unique('token');
            }

            // Check and add morph index
            $morphIndex = DB::select("SHOW INDEX FROM personal_access_tokens WHERE Key_name = 'personal_access_tokens_tokenable_type_tokenable_id_index'");
            if (empty($morphIndex)) {
                $table->index(['tokenable_type', 'tokenable_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reversing this is complex and potentially destructive, so we leave it empty.
    }
};
