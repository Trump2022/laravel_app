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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('pid')->default(0)->index()->comment('父级权限/菜单ID,0表示根节点');
            $table->string('id_path', 128)->default('0,')->comment('祖先ID路径,0开头,逗号分隔,以逗号结尾,不包含自身,例如:0,2,3,');
            $table->string('name')->comment('权限/菜单名称');
            $table->string('icon')->nullable()->comment('图标');
            $table->string('path')->nullable()->comment('Vue前端页面URL,纯展开目录可以为空,API权限没有前端页面,也为空');
            $table->string('component', 150)->nullable()->comment('组件路径');
            $table->string('permission_code', 191)->nullable()->unique()->comment('API权限使用“Laravel路由名:HTTP方法转小写”,例如 admin.users.update:put ,目录或页面菜单按需填写');
            $table->unsignedTinyInteger('type')->comment('1=目录,2=页面菜单,3=API权限');
            $table->unsignedTinyInteger('status')->default(0)->comment('0=停用,1=启用,新建时默认停用');
            $table->unsignedInteger('sort')->default(0)->comment('排序,始终连续且从0开始,新增、删除或调整顺序时，再由后续菜单管理逻辑维护连续性');
            $table->string('remark')->nullable()->comment('备注');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
