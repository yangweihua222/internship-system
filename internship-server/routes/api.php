<?php

use App\Http\Controllers\Api\V1\AiToolController;
use App\Http\Controllers\Api\V1\AttendanceGroupController;
use App\Http\Controllers\Api\V1\AttendanceScheduleController;
use App\Http\Controllers\Api\V1\AttendanceStatsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CheckinController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\DutyLogController;
use App\Http\Controllers\Api\V1\DutyScheduleController;
use App\Http\Controllers\Api\V1\EvaluationController;
use App\Http\Controllers\Api\V1\InternshipReportController;
use App\Http\Controllers\Api\V1\MedicalProgressController;
use App\Http\Controllers\Api\V1\NoticeController;
use App\Http\Controllers\Api\V1\OaController;
use App\Http\Controllers\Api\V1\OperationRecordController;
use App\Http\Controllers\Api\V1\PatientCaseController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\RotationController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\TeachingEventController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // 登录认证
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // 角色（下拉用）
        Route::get('roles', [RoleController::class, 'index']);

        // 轮转（学生查自己，管理员查全部）
        Route::get('rotations/calendar', [RotationController::class, 'calendar']);
        Route::get('rotations/department-students', [RotationController::class, 'departmentStudents']);

        // 考勤：打卡（本人）+ 排班/班次/考勤组只读
        Route::post('attendance/checkin', [CheckinController::class, 'checkin']);
        Route::get('attendance/my-records', [CheckinController::class, 'myRecords']);
        Route::get('attendance/my-logs', [CheckinController::class, 'myLogs']);
        Route::get('shifts', [ShiftController::class, 'index']);
        Route::get('attendance-groups', [AttendanceGroupController::class, 'index']);
        Route::get('attendance/schedules', [AttendanceScheduleController::class, 'index']);
        Route::get('attendance/statistics', [AttendanceStatsController::class, 'monthly']);

        // 值班：所有人可查自己和按月列表
        Route::get('duty-schedules', [DutyScheduleController::class, 'index']);
        Route::get('duty-schedules/mine', [DutyScheduleController::class, 'mine']);
        Route::get('duty-logs', [DutyLogController::class, 'index']);
        Route::post('duty-logs', [DutyLogController::class, 'store']);
        Route::put('duty-logs/{dutyLog}', [DutyLogController::class, 'update']);
        Route::delete('duty-logs/{dutyLog}', [DutyLogController::class, 'destroy']);

        // OA 审批：发起/审批/撤销
        Route::get('oa/approvers', [OaController::class, 'approvers']);
        Route::get('oa/my', [OaController::class, 'myProcesses']);
        Route::get('oa/pending', [OaController::class, 'pending']);
        Route::get('oa/acted', [OaController::class, 'acted']);
        Route::post('oa/leave', [OaController::class, 'submitLeave']);
        Route::post('oa/leave-cancel', [OaController::class, 'submitLeaveCancel']);
        Route::post('oa/shift-change', [OaController::class, 'submitShiftChange']);
        Route::post('oa/append-checkin', [OaController::class, 'submitAppendCheckin']);
        Route::post('oa/nodes/{node}/approve', [OaController::class, 'approve']);
        Route::post('oa/nodes/{node}/reject', [OaController::class, 'reject']);
        Route::post('oa/{process}/cancel', [OaController::class, 'cancel']);

        // 实习任务：病历 / 病程 / 操作记录
        Route::get('patient-cases', [PatientCaseController::class, 'index']);
        Route::post('patient-cases', [PatientCaseController::class, 'store']);
        Route::put('patient-cases/{id}', [PatientCaseController::class, 'update']);
        Route::delete('patient-cases/{id}', [PatientCaseController::class, 'destroy']);
        Route::get('medical-progresses', [MedicalProgressController::class, 'index']);
        Route::post('medical-progresses', [MedicalProgressController::class, 'store']);
        Route::put('medical-progresses/{id}', [MedicalProgressController::class, 'update']);
        Route::delete('medical-progresses/{id}', [MedicalProgressController::class, 'destroy']);
        Route::get('operation-records', [OperationRecordController::class, 'index']);
        Route::post('operation-records', [OperationRecordController::class, 'store']);
        Route::put('operation-records/{id}', [OperationRecordController::class, 'update']);
        Route::delete('operation-records/{id}', [OperationRecordController::class, 'destroy']);
        Route::middleware('role:teacher|admin|teaching-admin')->group(function () {
            Route::post('patient-cases/{id}/review', [PatientCaseController::class, 'review']);
            Route::post('medical-progresses/{id}/review', [MedicalProgressController::class, 'review']);
            Route::post('operation-records/{id}/review', [OperationRecordController::class, 'review']);
            Route::post('internship-reports/{report}/review', [InternshipReportController::class, 'review']);
            Route::get('teaching-events/{event}/records', [TeachingEventController::class, 'records']);
            Route::post('teaching-event-records/{record}/score', [TeachingEventController::class, 'scoreRecord']);
            Route::post('evaluation-templates', [EvaluationController::class, 'storeTemplate']);
            Route::post('evaluation-templates/{template}/toggle', [EvaluationController::class, 'toggleTemplate']);
            Route::post('teaching-events', [TeachingEventController::class, 'store']);
            Route::put('teaching-events/{event}/status', [TeachingEventController::class, 'updateStatus']);
        });

        // 实习报告
        Route::get('internship-reports', [InternshipReportController::class, 'index']);
        Route::post('internship-reports', [InternshipReportController::class, 'store']);
        Route::put('internship-reports/{report}', [InternshipReportController::class, 'update']);
        Route::delete('internship-reports/{report}', [InternshipReportController::class, 'destroy']);

        // 考核评价
        Route::get('evaluation-templates', [EvaluationController::class, 'templates']);
        Route::post('evaluations', [EvaluationController::class, 'submit']);
        Route::get('evaluations', [EvaluationController::class, 'results']);
        Route::get('evaluations/{evaluation}', [EvaluationController::class, 'show']);

        // 教学活动
        Route::get('teaching-events', [TeachingEventController::class, 'index']);
        Route::post('teaching-events/{event}/sign', [TeachingEventController::class, 'sign']);

        // 通知公告
        Route::get('notices', [NoticeController::class, 'index']);
        Route::middleware('role:admin|teaching-admin')->group(function () {
            Route::post('notices', [NoticeController::class, 'store']);
            Route::delete('notices/{notice}', [NoticeController::class, 'destroy']);
        });

        // 统计看板
        Route::get('stats/dashboard', [StatsController::class, 'dashboard']);

        // AI 教学助手工具网关（白名单，权限=token 所属用户）
        Route::post('ai/tools/call', [AiToolController::class, 'call']);
        Route::get('ai/tools/schema', [AiToolController::class, 'schema']);

        // 组织架构：科室
        Route::get('departments', [DepartmentController::class, 'index']);
        Route::middleware('role:admin|teaching-admin')->group(function () {
            Route::post('departments', [DepartmentController::class, 'store']);
            Route::put('departments/{department}', [DepartmentController::class, 'update']);
            Route::delete('departments/{department}', [DepartmentController::class, 'destroy']);

            // 用户管理
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::put('users/{user}', [UserController::class, 'update']);
            Route::delete('users/{user}', [UserController::class, 'destroy']);

            // 轮转管理
            Route::get('rotation-plans', [RotationController::class, 'plans']);
            Route::post('rotation-plans', [RotationController::class, 'storePlan']);
            Route::put('rotation-plans/{plan}', [RotationController::class, 'updatePlan']);
            Route::delete('rotation-plans/{plan}', [RotationController::class, 'destroyPlan']);
            Route::get('rotation-plans/{plan}/members', [RotationController::class, 'members']);
            Route::post('rotation-plans/{plan}/members', [RotationController::class, 'addMembers']);
            Route::delete('rotation-plans/{plan}/members/{student}', [RotationController::class, 'removeMember']);
            Route::get('rotation-plans/{plan}/details', [RotationController::class, 'details']);
            Route::post('rotation-plans/{plan}/assign', [RotationController::class, 'assign']);
            Route::delete('rotation-details/{detail}', [RotationController::class, 'destroyDetail']);

            // 考勤管理
            Route::post('shifts', [ShiftController::class, 'store']);
            Route::put('shifts/{shift}', [ShiftController::class, 'update']);
            Route::delete('shifts/{shift}', [ShiftController::class, 'destroy']);
            Route::post('attendance-groups', [AttendanceGroupController::class, 'store']);
            Route::put('attendance-groups/{group}', [AttendanceGroupController::class, 'update']);
            Route::delete('attendance-groups/{group}', [AttendanceGroupController::class, 'destroy']);
            Route::post('attendance-groups/{group}/members', [AttendanceGroupController::class, 'assignMembers']);
            Route::post('attendance/schedules', [AttendanceScheduleController::class, 'bulkStore']);
            Route::delete('attendance/schedules/{schedule}', [AttendanceScheduleController::class, 'destroy']);

            // 值班管理（排班写操作）
            Route::post('duty-schedules', [DutyScheduleController::class, 'bulkStore']);
            Route::delete('duty-schedules/{dutySchedule}', [DutyScheduleController::class, 'destroy']);
        });
    });
});
