#!/usr/bin/env bash
# 全模块数据填充测试：对每一个“添加”接口真实写入并断言成功
BASE="http://127.0.0.1:8000/api/v1"
PHP=/d/phpstudy_pro/Extensions/php/php8.2.9nts/php.exe
J="Content-Type: application/json"
AJ="Accept: application/json"
PASS=0; FAIL=0
check() {
  local name="$1"; local expect="$2"; local actual="$3"
  if echo "$actual" | grep -qF "$expect"; then PASS=$((PASS+1)); echo "  PASS  $name";
  else FAIL=$((FAIL+1)); echo "  FAIL  $name => $(echo "$actual" | head -c 200)"; fi
}
tok() { curl -s --noproxy "*" -X POST "$BASE/auth/login" -H "$J" -d "{\"username\":\"$1\",\"password\":\"$2\"}" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->token ?? '';"; }
T=$(tok admin admin123456);  HA="Authorization: Bearer $T"
TT=$(tok teacher01 teacher123); HT="Authorization: Bearer $TT"
TS=$(tok student01 student123); HS="Authorization: Bearer $TS"
TODAY=$("$PHP" -r "echo date('Y-m-d');")

echo "===== 组织 ====="
R=$(curl -s --noproxy "*" -X POST "$BASE/departments" -H "$HA" -H "$J" -H "$AJ" -d '{"name":"测试科-儿科"}'); check "新增科室" '"id"' "$R"
DID=$(echo "$R" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';")
R=$(curl -s --noproxy "*" -X POST "$BASE/users" -H "$HA" -H "$J" -H "$AJ" -d '{"username":"student02","name":"测试学生2","password":"student123","type":"student","roles":["student"]}'); check "新增学生用户" '"id"' "$R"
SID2=$(echo "$R" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';")
R=$(curl -s --noproxy "*" -X POST "$BASE/users" -H "$HA" -H "$J" -H "$AJ" -d '{"username":"teacher02","name":"测试老师2","password":"teacher123","type":"teacher","department_id":1,"roles":["teacher"]}'); check "新增老师用户" '"id"' "$R"
TID2=$(echo "$R" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';")

echo "===== 轮转 ====="
R=$(curl -s --noproxy "*" -X POST "$BASE/rotation-plans" -H "$HA" -H "$J" -H "$AJ" -d '{"name":"填充测试轮转","start_date":"2026-10-19","end_date":"2026-11-29"}'); check "新建轮转" '"id"' "$R"
RID=$(echo "$R" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';")
check "轮转加学生" '"added":2' "$(curl -s --noproxy "*" -X POST "$BASE/rotation-plans/$RID/members" -H "$HA" -H "$J" -H "$AJ" -d "{\"student_ids\":[3,$SID2]}")"
check "分段安排" '"message":"ok"' "$(curl -s --noproxy "*" -X POST "$BASE/rotation-plans/$RID/assign" -H "$HA" -H "$J" -H "$AJ" -d "{\"items\":[{\"student_id\":3,\"department_id\":1,\"start_date\":\"2026-10-19\",\"end_date\":\"2026-11-01\"},{\"student_id\":3,\"department_id\":4,\"start_date\":\"2026-11-02\",\"end_date\":\"2026-11-15\"},{\"student_id\":$SID2,\"department_id\":1,\"start_date\":\"2026-10-19\",\"end_date\":\"2026-11-01\"}]}")"

echo "===== 考勤 ====="
R=$(curl -s --noproxy "*" -X POST "$BASE/shifts" -H "$HA" -H "$J" -H "$AJ" -d '{"name":"填充夜班","start_time":"20:00","end_time":"08:00","late_threshold_minutes":15}'); check "新增班次" '"id"' "$R"
FID=$(echo "$R" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';")
R=$(curl -s --noproxy "*" -X POST "$BASE/attendance-groups" -H "$HA" -H "$J" -H "$AJ" -d '{"name":"填充考勤组","department_id":1,"lat":39.9042,"lng":116.4074,"radius_m":500}'); check "新增加考勤组" '"id"' "$R"
GID=$(echo "$R" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';")
check "考勤组加成员" '"message":"ok"' "$(curl -s --noproxy "*" -X POST "$BASE/attendance-groups/$GID/members" -H "$HA" -H "$J" -H "$AJ" -d "{\"user_ids\":[3,$SID2]}")"
check "批量排班" '"created"' "$(curl -s --noproxy "*" -X POST "$BASE/attendance/schedules" -H "$HA" -H "$J" -H "$AJ" -d "{\"user_ids\":[3],\"group_id\":$GID,\"shift_id\":1,\"start_date\":\"$TODAY\",\"end_date\":\"$TODAY\"}")"
R=$(curl -s --noproxy "*" -X POST "$BASE/attendance/checkin" -H "$HS" -H "$J" -H "$AJ" -d '{"check_type":"in","lat":39.9042,"lng":116.4074}'); check "学生上班打卡" '"status"' "$R"
R=$(curl -s --noproxy "*" -X POST "$BASE/attendance/checkin" -H "$HS" -H "$J" -H "$AJ" -d '{"check_type":"out","lat":39.9043,"lng":116.4075}'); check "学生下班打卡" '"status"' "$R"

echo "===== 值班 ====="
check "批量排值班" '"created"' "$(curl -s --noproxy "*" -X POST "$BASE/duty-schedules" -H "$HA" -H "$J" -H "$AJ" -d "{\"user_ids\":[3],\"department_id\":1,\"shift_label\":\"白班\",\"start_date\":\"$TODAY\",\"end_date\":\"$TODAY\"}")"
check "写值班日志" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/duty-logs" -H "$HS" -H "$J" -H "$AJ" -d "{\"duty_date\":\"$TODAY\",\"title\":\"填充值班日志\",\"content\":\"填充测试：今日值班一切正常\"}")"

echo "===== OA 审批 ====="
R=$(curl -s --noproxy "*" -X POST "$BASE/oa/leave" -H "$HS" -H "$J" -H "$AJ" -d "{\"leave_type\":\"事假\",\"start_date\":\"$TODAY\",\"end_date\":\"$TODAY\",\"reason\":\"填充测试请假\",\"approver_ids\":[2]}"); check "发起请假" '"id"' "$R"
PID=$(echo "$R" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';")
# pending 返回的是待审批节点列表，每项含 process；找到属于本流程的节点 ID
NID=$(curl -s --noproxy "*" "$BASE/oa/pending" -H "$HT" -H "$AJ" | "$PHP" -r "\$d=json_decode(file_get_contents('php://stdin')); foreach(\$d->list ?? [] as \$n){ if((\$n->process->id ?? 0)==$PID){ echo \$n->id; break; } }")
check "老师审批请假" '"message":"ok"' "$(curl -s --noproxy "*" -X POST "$BASE/oa/nodes/$NID/approve" -H "$HT" -H "$J" -H "$AJ" -d '{"comment":"同意"}')"
check "发起补卡" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/oa/append-checkin" -H "$HS" -H "$J" -H "$AJ" -d "{\"work_date\":\"$TODAY\",\"check_type\":\"in\",\"check_time\":\"08:05\",\"reason\":\"填充测试补卡\",\"approver_ids\":[2]}")"

echo "===== 实习任务 ====="
R=$(curl -s --noproxy "*" -X POST "$BASE/patient-cases" -H "$HS" -H "$J" -H "$AJ" -d '{"patient_name":"填充患者","admission_no":"FILL001","title":"填充病历","content":"主诉：填充测试","status":"submitted"}'); check "写病历" '"id"' "$R"
CID=$(echo "$R" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';")
check "老师批改病历" '"message":"ok"' "$(curl -s --noproxy "*" -X POST "$BASE/patient-cases/$CID/review" -H "$HT" -H "$J" -H "$AJ" -d '{"action":"approve","score":90,"comment":"填充批改"}')"
check "写病程" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/medical-progresses" -H "$HS" -H "$J" -H "$AJ" -d '{"patient_name":"填充患者","title":"填充病程","content":"今日查房...","record_date":"'$TODAY'","status":"submitted"}')"
check "写操作记录" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/operation-records" -H "$HS" -H "$J" -H "$AJ" -d '{"patient_name":"填充患者","project_name":"换药术","operation_date":"'$TODAY'","title":"填充操作","content":"换药操作","status":"draft"}')"

echo "===== 评价 ====="
R=$(curl -s --noproxy "*" -X POST "$BASE/evaluation-templates" -H "$HT" -H "$J" -H "$AJ" -d '{"name":"填充评教表","target":"teacher","questions":[{"content":"教学态度","max_score":50},{"content":"带教水平","max_score":50}]}'); check "建评价模板" '"id"' "$R"
EID=$(echo "$R" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';")
Q1=$(curl -s --noproxy "*" "$BASE/evaluation-templates" -H "$HA" -H "$AJ" | "$PHP" -r "\$d=json_decode(file_get_contents('php://stdin')); foreach(\$d->list as \$t){ if(\$t->id==$EID){ echo \$t->questions[0]->id; break; } }")
Q2=$(curl -s --noproxy "*" "$BASE/evaluation-templates" -H "$HA" -H "$AJ" | "$PHP" -r "\$d=json_decode(file_get_contents('php://stdin')); foreach(\$d->list as \$t){ if(\$t->id==$EID){ echo \$t->questions[1]->id; break; } }")
check "提交评价" '"total_score":95' "$(curl -s --noproxy "*" -X POST "$BASE/evaluations" -H "$HS" -H "$J" -H "$AJ" -d "{\"template_id\":$EID,\"target_user_id\":2,\"period\":\"$TODAY\",\"answers\":[{\"question_id\":$Q1,\"score\":48},{\"question_id\":$Q2,\"score\":47}]}")"

echo "===== 教学活动/报告/公告 ====="
R=$(curl -s --noproxy "*" -X POST "$BASE/teaching-events" -H "$HT" -H "$J" -H "$AJ" -d "{\"title\":\"填充教学活动\",\"content\":\"教学查房\",\"start_at\":\"$TODAY 14:00:00\",\"end_at\":\"$TODAY 16:00:00\",\"location\":\"示教室\",\"department_id\":1}"); check "发教学活动" '"id"' "$R"
VID=$(echo "$R" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';")
check "学生签到" '"message":"ok"' "$(curl -s --noproxy "*" -X POST "$BASE/teaching-events/$VID/sign" -H "$HS" -H "$J" -H "$AJ" -d '{"action":"in"}')"
check "写实习报告" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/internship-reports" -H "$HS" -H "$J" -H "$AJ" -d '{"type":"summary","title":"填充实习总结","content":"在内科实习...","period_start":"2026-10-19","period_end":"2026-11-01"}')"
check "发公告" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/notices" -H "$HA" -H "$J" -H "$AJ" -d '{"title":"填充公告","content":"这是一条填充测试公告"}')"

echo
echo "===== 结果：PASS=$PASS FAIL=$FAIL ====="
