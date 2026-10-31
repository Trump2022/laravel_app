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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->unique()->comment('设置项唯一键');
            $table->string('name', 150)->index()->comment('设置项名称');
            $table->unsignedTinyInteger('type')->comment('1=字符串,2=整数,3=布尔值,4=小数,5-二维数组,6=特殊JSON');
            $table->string('setting_value', 4096)->nullable()->comment('type=1-4 时使用的标量值');
            $table->json('json_value')->nullable()->comment('type=5或6 时使用的 JSON 值');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
