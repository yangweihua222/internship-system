# 实习管理平台 · 后端（Laravel 12）

> 这是 [临床实习教学管理平台](../README.md) 的服务端。完整的架构说明、功能清单与快速开始请看**仓库根目录的 README**。

## 这一层负责什么

- 业务领域建模与持久化（18 张表，Eloquent 关系 + 迁移）
- 认证与授权：Sanctum Token + spatie/laravel-permission 四角色
- 业务规则校验：轮转分段不重叠、考勤围栏与迟到早退判定、OA 多级审批链与回写
- **AI 工具网关**：对外暴露 13 个工具，供 AI 助手回调，权限随调用者 token

## 技术要点

| 项 | 实现 |
|---|---|
| 鉴权 | Laravel Sanctum，Bearer Token |
| 权限 | `role` 中间件 + spatie 角色；菜单与接口双层过滤 |
| 校验 | FormRequest 统一参数校验，异常统一转 `{ok:false,error}` |
| 日期 | 一律 `date:Y-m-d` cast，`APP_TIMEZONE=Asia/Shanghai`（打卡日期语义） |
| 复用 | 病历/病程/操作三类任务抽象为 `AbstractTaskController` |
| 审批回写 | 请假通过后标记考勤、销假还原、换班改值班表 |

## AI 工具网关

AI 助手不直接访问数据库，所有数据通过这里拿：

```
POST /api/v1/ai/tools/call
Authorization: Bearer <用户 token>
{ "tool": "internship_my_rotation", "arguments": { ... } }
```

13 个工具白名单：查轮转 / 值班 / 考勤 / 出勤统计 / 请假记录 / 教学活动 / 公告 / 考核 / 病历 / 待办汇总 / **提交请假**，以及教师专属的科室学生查询、待审批查询。

权限由 token 所属用户决定——学生调用教师专属工具返回 403。工具返回给 LLM 的日期统一经 `fmt()` 转为 `Y-m-d H:i`，避免 UTC ISO 串造成模型误读。

## 本地启动

```bash
composer install
cp .env.example .env && php artisan key:generate
# 编辑 .env 的 DB_*（MySQL 或 SQLite）
php artisan migrate --seed
php artisan serve          # http://127.0.0.1:8000
```

重置数据：`php artisan migrate:fresh --seed`（会清空全部业务数据）。

## 回归

仓库根目录执行 `/usr/bin/bash scripts/smoke-api.sh`，52 项断言含 AI 工具网关 10 项。

## License

MIT
