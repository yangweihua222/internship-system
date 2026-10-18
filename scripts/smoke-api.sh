#!/usr/bin/env bash
# 实习管理系统 API 冒烟回归脚本
# 用法：先启动后端（php artisan serve），然后在本项目根目录执行：
#   /usr/bin/bash scripts/smoke-api.sh
# 依赖：curl + PHP（用于解析 token）
PHP="/d/phpstudy_pro/Extensions/php/php8.2.9nts/php.exe"
BASE="${BASE:-http://127.0.0.1:8000/api/v1}"
J="Content-Type: application/json"
PASS=0; FAIL=0

login() { curl -s --noproxy "*" -X POST "$BASE/auth/login" -H "$J" -d "{\"username\":\"$1\",\"password\":\"$2\"}" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->token ?? '';"; }

check() { # check <desc> <expected_substr> <actual>
  if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  PASS  $1";
  else FAIL=$((FAIL+1)); echo "  FAIL  $1 期望含[$2] 实际: $(echo "$3" | /usr/bin/head -c 200)"; fi
}

echo "===== 登录 ====="
AT=$(login admin admin123456); TT=$(login teacher01 teacher123); ST=$(login student01 student123)
check "admin 登录" "|" "$AT"; check "teacher 登录" "|" "$TT"; check "student 登录" "|" "$ST"
HA="Authorization: Bearer $AT"; HT="Authorization: Bearer $TT"; HS="Authorization: Bearer $ST"
AJ="Accept: application/json"

echo "===== P0 组织 ====="
check "科室树" '"children"' "$(curl -s --noproxy "*" "$BASE/departments" -H "$HA")"
check "用户列表" '"total"' "$(curl -s --noproxy "*" "$BASE/users" -H "$HA")"
check "老师访问用户管理被拒 403" "403" "$(curl -s --noproxy "*" -o /dev/null -w '%{http_code}' "$BASE/users" -H "$HT")"
check "角色列表" '"admin"' "$(curl -s --noproxy "*" "$BASE/roles" -H "$HA")"

echo "===== P1 轮转 ====="
ROT=$(curl -s --noproxy "*" -X POST "$BASE/rotation-plans" -H "$HA" -H "$J" -H "$AJ" -d '{"name":"冒烟轮转","start_date":"2026-10-19","end_date":"2026-11-15"}')
check "创建轮转(名称+周期)" '"id"' "$ROT"
ROT_ID=$(echo "$ROT" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';")
check "轮转添加学生" '"added":1' "$(curl -s --noproxy "*" -X POST "$BASE/rotation-plans/$ROT_ID/members" -H "$HA" -H "$J" -d '{"student_ids":[3]}')"
check "分段分配(内科+外科)" '"message":"ok"' "$(curl -s --noproxy "*" -X POST "$BASE/rotation-plans/$ROT_ID/assign" -H "$HA" -H "$J" -d '{"items":[{"student_id":3,"department_id":1,"start_date":"2026-10-19","end_date":"2026-11-01"},{"student_id":3,"department_id":4,"start_date":"2026-11-02","end_date":"2026-11-15"}]}')"
check "超出轮转周期被拒 422" "422" "$(curl -s --noproxy "*" -o /dev/null -w '%{http_code}' -X POST "$BASE/rotation-plans/$ROT_ID/assign" -H "$HA" -H "$J" -d '{"items":[{"student_id":3,"department_id":2,"start_date":"2026-11-16","end_date":"2026-11-30"}]}')"
check "非成员分配被拒 422" "422" "$(curl -s --noproxy "*" -o /dev/null -w '%{http_code}' -X POST "$BASE/rotation-plans/$ROT_ID/assign" -H "$HA" -H "$J" -d '{"items":[{"student_id":2,"department_id":2,"start_date":"2026-10-20","end_date":"2026-10-21"}]}')"
check "重叠分段被拒 422" "422" "$(curl -s --noproxy "*" -o /dev/null -w '%{http_code}' -X POST "$BASE/rotation-plans/$ROT_ID/assign" -H "$HA" -H "$J" -d '{"items":[{"student_id":3,"department_id":2,"start_date":"2026-10-25","end_date":"2026-10-28"}]}')"
check "轮转计划列表" '"students_count"' "$(curl -s --noproxy "*" "$BASE/rotation-plans" -H "$HA")"
check "轮转成员列表" '"list"' "$(curl -s --noproxy "*" "$BASE/rotation-plans/$ROT_ID/members" -H "$HA")"
check "我的轮转日历" '"list"' "$(curl -s --noproxy "*" "$BASE/rotations/calendar" -H "$HS")"
check "科室在科学生" '"list"' "$(curl -s --noproxy "*" "$BASE/rotations/department-students?department_id=1&date=2026-10-25" -H "$HA")"

echo "===== P1 考勤 ====="
check "班次列表" '"list"' "$(curl -s --noproxy "*" "$BASE/shifts" -H "$HA")"
check "考勤组列表" '"list"' "$(curl -s --noproxy "*" "$BASE/attendance-groups" -H "$HA")"
M=$("$PHP" -r "echo date('Y-m');")
check "我的打卡日历" '"days"' "$(curl -s --noproxy "*" "$BASE/attendance/my-records?month=$M" -H "$HS" -H "$AJ")"
check "考勤统计" '"list"' "$(curl -s --noproxy "*" "$BASE/attendance/statistics?month=$M" -H "$HA" -H "$AJ")"

echo "===== P2 值班 ====="
check "值班表" '"list"' "$(curl -s --noproxy "*" "$BASE/duty-schedules?month=$M" -H "$HA" -H "$AJ")"
check "我的值班" '"list"' "$(curl -s --noproxy "*" "$BASE/duty-schedules/mine?month=$M" -H "$HS" -H "$AJ")"
check "值班日志" '"list"' "$(curl -s --noproxy "*" "$BASE/duty-logs" -H "$HS" -H "$AJ")"

echo "===== P2 OA ====="
check "我的申请" '"list"' "$(curl -s --noproxy "*" "$BASE/oa/my" -H "$HS")"
check "待我审批" '"list"' "$(curl -s --noproxy "*" "$BASE/oa/pending" -H "$HT")"
check "审批人列表" '"list"' "$(curl -s --noproxy "*" "$BASE/oa/approvers" -H "$HS")"

echo "===== P3 实习任务 ====="
check "病程列表" '"list"' "$(curl -s --noproxy "*" "$BASE/medical-progresses" -H "$HS")"
check "操作记录列表" '"list"' "$(curl -s --noproxy "*" "$BASE/operation-records" -H "$HS")"
CASE=$(curl -s --noproxy "*" -X POST "$BASE/patient-cases" -H "$HS" -H "$J" -H "$AJ" -d '{"patient_name":"冒烟患者","admission_no":"SMOKE001","title":"冒烟测试病历","content":"主诉：冒烟测试","status":"submitted"}')
check "学生写病历" '"id"' "$CASE"
CASE_ID=$(echo "$CASE" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';")
check "病历列表(学生看自己)" '"patient_name"' "$(curl -s --noproxy "*" "$BASE/patient-cases" -H "$HS")"
check "病历列表(老师看本科室)" '"patient_name"' "$(curl -s --noproxy "*" "$BASE/patient-cases" -H "$HT")"
check "老师批改病历" '"ok"' "$(curl -s --noproxy "*" -X POST "$BASE/patient-cases/$CASE_ID/review" -H "$HT" -H "$J" -H "$AJ" -d '{"action":"approve","score":90,"comment":"冒烟通过"}')"
check "学生批改被拒 403" "403" "$(curl -s --noproxy "*" -o /dev/null -w '%{http_code}' -X POST "$BASE/patient-cases/$CASE_ID/review" -H "$HS" -H "$J" -H "$AJ" -d '{"action":"approve"}')"

echo "===== P3 评价 ====="
TPL=$(curl -s --noproxy "*" -X POST "$BASE/evaluation-templates" -H "$HT" -H "$J" -H "$AJ" -d '{"name":"冒烟评教表","target":"teacher","questions":[{"content":"教学态度","max_score":50},{"content":"带教水平","max_score":50}]}')
check "建评价模板" '"id"' "$TPL"
TPL_ID=$(echo "$TPL" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';")
QIDS=$(curl -s --noproxy "*" "$BASE/evaluation-templates?target=teacher" -H "$HS" | "$PHP" -r "\$l=json_decode(file_get_contents('php://stdin'))->list; foreach(\$l as \$t){ if(\$t->id==$TPL_ID){ echo implode(',', array_map(fn(\$q)=>\$q->id, \$t->questions)); } }")
Q1=${QIDS%,*}; Q2=${QIDS#*,}
check "提交评价" '"total_score"' "$(curl -s --noproxy "*" -X POST "$BASE/evaluations" -H "$HS" -H "$J" -H "$AJ" -d "{\"template_id\":$TPL_ID,\"target_user_id\":2,\"period\":\"$M\",\"answers\":[{\"question_id\":$Q1,\"score\":48},{\"question_id\":$Q2,\"score\":47}]}")"
check "评价模板列表" '"target"' "$(curl -s --noproxy "*" "$BASE/evaluation-templates" -H "$HA")"
check "评价结果" '"list"' "$(curl -s --noproxy "*" "$BASE/evaluations?scope=received" -H "$HS")"

echo "===== P3 活动/报告/公告/看板 ====="
check "教学活动" '"list"' "$(curl -s --noproxy "*" "$BASE/teaching-events" -H "$HS")"
check "实习报告" '"list"' "$(curl -s --noproxy "*" "$BASE/internship-reports" -H "$HS")"
check "通知公告" '"list"' "$(curl -s --noproxy "*" "$BASE/notices" -H "$HS")"
check "统计看板" '"attendance_trend"' "$(curl -s --noproxy "*" "$BASE/stats/dashboard" -H "$HA")"

echo "===== P4 AI 工具网关 ====="
check "AI schema" '"tools"' "$(curl -s --noproxy "*" "$BASE/ai/tools/schema" -H "$HS")"
check "AI 查轮转" '"department"' "$(curl -s --noproxy "*" -X POST "$BASE/ai/tools/call" -H "$HS" -H "$J" -H "$AJ" -d '{"tool":"get_my_rotation","arguments":{}}')"
check "AI 查值班" '"month"' "$(curl -s --noproxy "*" -X POST "$BASE/ai/tools/call" -H "$HS" -H "$J" -H "$AJ" -d '{"tool":"get_my_duty","arguments":{}}')"
check "AI 考勤统计" '"scheduled_days"' "$(curl -s --noproxy "*" -X POST "$BASE/ai/tools/call" -H "$HS" -H "$J" -H "$AJ" -d '{"tool":"get_attendance_stats","arguments":{}}')"
check "AI 待办汇总" '"unreviewed_patient_cases"' "$(curl -s --noproxy "*" -X POST "$BASE/ai/tools/call" -H "$HS" -H "$J" -H "$AJ" -d '{"tool":"get_my_tasks_summary","arguments":{}}')"
check "AI 查通知" '"list"' "$(curl -s --noproxy "*" -X POST "$BASE/ai/tools/call" -H "$HS" -H "$J" -H "$AJ" -d '{"tool":"list_notices","arguments":{}}')"
check "AI 未知工具 404" "404" "$(curl -s --noproxy "*" -o /dev/null -w '%{http_code}' -X POST "$BASE/ai/tools/call" -H "$HS" -H "$J" -H "$AJ" -d '{"tool":"hack","arguments":{}}')"
check "AI 学生调老师工具 403" "403" "$(curl -s --noproxy "*" -o /dev/null -w '%{http_code}' -X POST "$BASE/ai/tools/call" -H "$HS" -H "$J" -H "$AJ" -d '{"tool":"get_pending_approvals","arguments":{}}')"
check "AI 老师查待审批" '"list"' "$(curl -s --noproxy "*" -X POST "$BASE/ai/tools/call" -H "$HT" -H "$J" -H "$AJ" -d '{"tool":"get_pending_approvals","arguments":{}}')"
check "AI 请假参数缺失 422" '"ok":false' "$(curl -s --noproxy "*" -X POST "$BASE/ai/tools/call" -H "$HS" -H "$J" -H "$AJ" -d '{"tool":"submit_leave","arguments":{"leave_type":"事假","start_date":"2026-10-21","end_date":"2026-10-21"}}')"

echo ""
echo "===== 结果：PASS=$PASS FAIL=$FAIL ====="
[ $FAIL -eq 0 ] && echo "全部通过" || exit 1
