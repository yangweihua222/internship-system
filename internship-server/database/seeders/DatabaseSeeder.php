<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 角色
        $roles = [
            ['name' => 'admin', 'title' => '平台管理员'],
            ['name' => 'teaching-admin', 'title' => '教学管理员'],
            ['name' => 'teacher', 'title' => '带教老师'],
            ['name' => 'student', 'title' => '学生'],
        ];
        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], ['title' => $role['title']]);
        }

        // 2. 示例科室树
        $internal = Department::updateOrCreate(['name' => '内科'], ['parent_id' => null, 'sort' => 1]);
        $surgery = Department::updateOrCreate(['name' => '外科'], ['parent_id' => null, 'sort' => 2]);
        Department::updateOrCreate(['name' => '儿科'], ['parent_id' => null, 'sort' => 3]);
        Department::updateOrCreate(['name' => '心内科'], ['parent_id' => $internal->id, 'sort' => 1]);
        Department::updateOrCreate(['name' => '呼吸内科'], ['parent_id' => $internal->id, 'sort' => 2]);
        Department::updateOrCreate(['name' => '骨科'], ['parent_id' => $surgery->id, 'sort' => 1]);

        // 3. 平台管理员账号（首次登录后请修改密码）
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => '系统管理员',
                'email' => 'admin@example.com',
                'password' => 'admin123456',
                'type' => User::TYPE_ADMIN,
                'department_id' => null,
                'status' => true,
            ],
        );
        $admin->syncRoles(['admin']);

        // 4. 演示老师 / 学生账号（演示数据，非真实人员）
        $teacher = User::updateOrCreate(
            ['username' => 'teacher01'],
            [
                'name' => '李静',
                'email' => 'teacher01@example.com',
                'password' => 'teacher123',
                'type' => User::TYPE_TEACHER,
                'department_id' => $internal->id,
                'status' => true,
            ],
        );
        $teacher->syncRoles(['teacher']);

        $student = User::updateOrCreate(
            ['username' => 'student01'],
            [
                'name' => '张明',
                'email' => 'student01@example.com',
                'password' => 'student123',
                'type' => User::TYPE_STUDENT,
                'department_id' => $internal->id,
                'status' => true,
            ],
        );
        $student->syncRoles(['student']);

        // 5. 演示用第二批师生
        $teacher2 = User::updateOrCreate(
            ['username' => 'teacher02'],
            [
                'name' => '王建国',
                'email' => 'teacher02@example.com',
                'password' => 'teacher123',
                'type' => User::TYPE_TEACHER,
                'department_id' => $surgery->id,
                'status' => true,
            ],
        );
        $teacher2->syncRoles(['teacher']);

        foreach ([
            ['username' => 'student02', 'name' => '陈雨欣'],
            ['username' => 'student03', 'name' => '刘子豪'],
        ] as $s) {
            $u = User::updateOrCreate(
                ['username' => $s['username']],
                [
                    'name' => $s['name'],
                    'email' => $s['username'] . '@example.com',
                    'password' => 'student123',
                    'type' => User::TYPE_STUDENT,
                    'department_id' => $internal->id,
                    'status' => true,
                ],
            );
            $u->syncRoles(['student']);
        }
    }
}
