#!/usr/bin/env bash
# 演示数据填充：造一套"能拿出去展示"的规整数据（人设统一、无真实姓名、覆盖全部模块）
# 与 fill-data.sh 的区别：fill-data 是接口回归测试脚本（脏数据），本脚本是展示用数据。
# 用法：先 ensure 服务在跑，再 bash scripts/demo-data.sh
BASE="http://127.0.0.1:8000/api/v1"
PHP=/d/phpstudy_pro/Extensions/php/php8.2.9nts/php.exe
J="Content-Type: application/json"
AJ="Accept: application/json"
PASS=0; FAIL=0
check() {
  local name="$1"; local expect="$2"; local actual="$3"
  if echo "$actual" | grep -qF "$expect"; then PASS=$((PASS+1)); echo "  PASS  $name";
  else FAIL=$((FAIL+1)); echo "  FAIL  $name => $(echo "$actual" | head -c 160)"; fi
}
tok() { curl -s --noproxy "*" -X POST "$BASE/auth/login" -H "$J" -d "{\"username\":\"$1\",\"password\":\"$2\"}" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->token ?? '';"; }
idof() { echo "$1" | "$PHP" -r "echo json_decode(file_get_contents('php://stdin'))->id ?? '';"; }

T=$(tok admin admin123456);  HA="Authorization: Bearer $T"
TT=$(tok teacher01 teacher123); HT="Authorization: Bearer $TT"
TS=$(tok student01 student123); HS="Authorization: Bearer $TS"
TODAY=$("$PHP" -r "echo date('Y-m-d');")
D1=$("$PHP" -r "echo date('Y-m-d', strtotime('-1 day'));")
D2=$("$PHP" -r "echo date('Y-m-d', strtotime('-2 days'));")
D3=$("$PHP" -r "echo date('Y-m-d', strtotime('-3 days'));")
NEXT=$("$PHP" -r "echo date('Y-m-d', strtotime('+7 days'));")

echo "===== 1. 组织与人员 ====="
# 科室树已由 seeder 建好（内科/外科/儿科/心内科/呼吸内科/骨科），这里补一个带教组
R=$(curl -s --noproxy "*" -X POST "$BASE/departments" -H "$HA" -H "$J" -H "$AJ" -d '{"name":"消化内科","parent_id":1,"sort":3}'); check "新建科室-消化内科" '"id"' "$R"
DEPT_DIGEST=$(idof "$R")
# 学生 id：1=admin 2=teacher01 3=student01 4=teacher02 5=student02 6=student03
S1=3; S2=5; S3=6; T1=2; T2=4

echo "===== 2. 轮转计划 ====="
R=$(curl -s --noproxy "*" -X POST "$BASE/rotation-plans" -H "$HA" -H "$J" -H "$AJ" -d '{"name":"2026 届临床医学毕业实习轮转","start_date":"2026-09-01","end_date":"2027-06-30"}'); check "新建轮转计划" '"id"' "$R"
RID=$(idof "$R")
check "加入 3 名实习生" '"added":3' "$(curl -s --noproxy "*" -X POST "$BASE/rotation-plans/$RID/members" -H "$HA" -H "$J" -H "$AJ" -d "{\"student_ids\":[$S1,$S2,$S3]}")"
check "张明分段安排（心内科→呼吸内科→骨科）" '"message":"ok"' "$(curl -s --noproxy "*" -X POST "$BASE/rotation-plans/$RID/assign" -H "$HA" -H "$J" -H "$AJ" -d "{\"items\":[
 {\"student_id\":$S1,\"department_id\":4,\"start_date\":\"2026-09-01\",\"end_date\":\"2026-10-31\"},
 {\"student_id\":$S1,\"department_id\":5,\"start_date\":\"2026-11-01\",\"end_date\":\"2026-12-31\"},
 {\"student_id\":$S1,\"department_id\":6,\"start_date\":\"2027-01-01\",\"end_date\":\"2027-02-28\"},
 {\"student_id\":$S2,\"department_id\":5,\"start_date\":\"2026-09-01\",\"end_date\":\"2026-10-31\"},
 {\"student_id\":$S2,\"department_id\":4,\"start_date\":\"2026-11-01\",\"end_date\":\"2026-12-31\"},
 {\"student_id\":$S3,\"department_id\":6,\"start_date\":\"2026-09-01\",\"end_date\":\"2026-10-31\"},
 {\"student_id\":$S3,\"department_id\":4,\"start_date\":\"2026-11-01\",\"end_date\":\"2026-12-31\"}
]}")"

echo "===== 3. 班次与考勤 ====="
R=$(curl -s --noproxy "*" -X POST "$BASE/shifts" -H "$HA" -H "$J" -H "$AJ" -d '{"name":"白班","start_time":"08:00","end_time":"17:30","late_threshold_minutes":15}'); check "新增白班" '"id"' "$R"
SHIFT_DAY=$(idof "$R")
R=$(curl -s --noproxy "*" -X POST "$BASE/shifts" -H "$HA" -H "$J" -H "$AJ" -d '{"name":"夜班","start_time":"17:30","end_time":"08:00","late_threshold_minutes":10}'); check "新增夜班" '"id"' "$R"
R=$(curl -s --noproxy "*" -X POST "$BASE/attendance-groups" -H "$HA" -H "$J" -H "$AJ" -d '{"name":"内科实习组","department_id":4,"lat":39.9042,"lng":116.4074,"radius_m":500}'); check "新建考勤组" '"id"' "$R"
GID=$(idof "$R")
check "考勤组加成员" '"message":"ok"' "$(curl -s --noproxy "*" -X POST "$BASE/attendance-groups/$GID/members" -H "$HA" -H "$J" -H "$AJ" -d "{\"user_ids\":[$S1,$S2,$S3]}")"
check "近 3 天批量排班" '"created"' "$(curl -s --noproxy "*" -X POST "$BASE/attendance/schedules" -H "$HA" -H "$J" -H "$AJ" -d "{\"user_ids\":[$S1,$S2,$S3],\"group_id\":$GID,\"shift_id\":$SHIFT_DAY,\"start_date\":\"$D3\",\"end_date\":\"$TODAY\"}")"

echo "===== 4. 值班 ====="
check "批量排值班" '"created"' "$(curl -s --noproxy "*" -X POST "$BASE/duty-schedules" -H "$HA" -H "$J" -H "$AJ" -d "{\"user_ids\":[$S1,$S2],\"department_id\":4,\"shift_label\":\"一线值班\",\"start_date\":\"$D1\",\"end_date\":\"$NEXT\"}")"
check "张明写值班日志" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/duty-logs" -H "$HS" -H "$J" -H "$AJ" -d "{\"duty_date\":\"$D1\",\"title\":\"心内科一线值班日志\",\"content\":\"19:30 接诊胸痛患者 1 例，协助完成心电图与心肌酶检查，22:00 病情平稳。夜间无新发危急值。\"}")"

echo "===== 5. OA 审批 ====="
check "张明请事假（1 天）" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/oa/leave" -H "$HS" -H "$J" -H "$AJ" -d "{\"leave_type\":\"事假\",\"start_date\":\"$NEXT\",\"end_date\":\"$NEXT\",\"reason\":\"参加执业医师资格技能考试\",\"approver_ids\":[$T1]}")"
NID=$(curl -s --noproxy "*" "$BASE/oa/pending" -H "$HT" -H "$AJ" | "$PHP" -r "\$d=json_decode(file_get_contents('php://stdin')); foreach(\$d->list ?? [] as \$n){ echo \$n->id; break; }")
check "李静老师审批通过" '"message":"ok"' "$(curl -s --noproxy "*" -X POST "$BASE/oa/nodes/$NID/approve" -H "$HT" -H "$J" -H "$AJ" -d '{"comment":"同意，注意提前完成交接"}')"
check "陈雨欣请病假（待审批）" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/oa/leave" -H "$HS" -H "$J" -H "$AJ" -d "{\"leave_type\":\"病假\",\"start_date\":\"$NEXT\",\"end_date\":\"$NEXT\",\"reason\":\"发热 38.5℃，遵医嘱休息一日\",\"approver_ids\":[$T1]}")"
check "刘子豪发起补卡" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/oa/append-checkin" -H "$HS" -H "$J" -H "$AJ" -d "{\"work_date\":\"$D2\",\"check_type\":\"in\",\"check_time\":\"08:05\",\"reason\":\"门诊楼电梯故障，迟到 5 分钟\",\"approver_ids\":[$T1]}")"

echo "===== 6. 实习任务（病历 / 病程 / 操作）====="
R=$(curl -s --noproxy "*" -X POST "$BASE/patient-cases" -H "$HS" -H "$J" -H "$AJ" -d "{\"patient_name\":\"示例患者A\",\"admission_no\":\"20260001\",\"title\":\"急性 ST 段抬高型心肌梗死\",\"content\":\"主诉：突发胸痛 3 小时。现病史：患者晨起后出现压榨性胸痛…查体：BP 132/84mmHg，心率 96 次/分。辅助检查：心电图示 V1-V4 导联 ST 段抬高 0.3mV，肌钙蛋白 I 升高。诊断：急性前壁 STEMI。处理：急诊 PCI 术后转入 CCU。\",\"status\":\"submitted\"}"); check "张明写病历-心梗" '"id"' "$R"
CID=$(idof "$R")
check "李静批改病历（评分 92）" '"message":"ok"' "$(curl -s --noproxy "*" -X POST "$BASE/patient-cases/$CID/review" -H "$HT" -H "$J" -H "$AJ" -d '{"action":"approve","score":92,"comment":"鉴别诊断到位，建议补充术后抗凝方案说明"}')"
check "张明写病历-心衰（待批改）" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/patient-cases" -H "$HS" -H "$J" -H "$AJ" -d "{\"patient_name\":\"示例患者B\",\"admission_no\":\"20260002\",\"title\":\"慢性心力衰竭急性加重\",\"content\":\"主诉：活动后气促加重 1 周。既往扩张型心肌病 3 年。查体：双下肢中度水肿，BNP 1860pg/mL。诊断：慢性心衰急性加重（NYHA III 级）。\",\"status\":\"submitted\"}")"
check "写病程记录" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/medical-progresses" -H "$HS" -H "$J" -H "$AJ" -d "{\"patient_name\":\"示例患者A\",\"title\":\"PCI 术后第 2 天病程\",\"content\":\"患者胸痛缓解，心电监护未见恶性心律失常，复查肌钙蛋白较峰值下降 40%，继续双抗治疗并监测出血倾向。\",\"record_date\":\"$D1\",\"status\":\"submitted\"}")"
check "写操作记录" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/operation-records" -H "$HS" -H "$J" -H "$AJ" -d "{\"patient_name\":\"示例患者A\",\"project_name\":\"桡动脉穿刺术\",\"operation_date\":\"$D2\",\"title\":\"冠脉造影术中协助穿刺\",\"content\":\"在带教老师指导下完成右侧桡动脉穿刺，一次成功，术后压迫止血 15 分钟无渗血。\",\"status\":\"submitted\"}")"

echo "===== 7. 教学活动 ====="
R=$(curl -s --noproxy "*" -X POST "$BASE/teaching-events" -H "$HT" -H "$J" -H "$AJ" -d "{\"title\":\"心内科教学查房：急性冠脉综合征\",\"content\":\"围绕 STEMI 的早期识别、再灌注策略选择与术后管理进行床旁教学，结合 2 例在院病例讨论。\",\"start_at\":\"$D1 14:00:00\",\"end_at\":\"$D1 16:00:00\",\"location\":\"心内科示教室\",\"department_id\":4}"); check "发布教学查房" '"id"' "$R"
VID=$(idof "$R")
check "张明签到" '"message":"ok"' "$(curl -s --noproxy "*" -X POST "$BASE/teaching-events/$VID/sign" -H "$HS" -H "$J" -H "$AJ" -d '{"action":"in"}')"
R=$(curl -s --noproxy "*" -X POST "$BASE/teaching-events" -H "$HT" -H "$J" -H "$AJ" -d "{\"title\":\"临床技能工作坊：心肺复苏与电除颤\",\"content\":\"模拟人实操训练，覆盖成人 CPR 流程、AED 使用与团队配合。\",\"start_at\":\"$NEXT 09:00:00\",\"end_at\":\"$NEXT 11:30:00\",\"location\":\"临床技能中心 302\",\"department_id\":4}"); check "发布技能工作坊" '"id"' "$R"

echo "===== 8. 实习报告 ====="
check "张明写月度总结" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/internship-reports" -H "$HS" -H "$J" -H "$AJ" -d "{\"type\":\"summary\",\"title\":\"心内科实习月度总结\",\"content\":\"本月参与收治患者 18 例，独立完成入院记录 12 份，参与 PCI 术前讨论 6 次。掌握了 ACS 的早期识别流程与常用评分工具。不足：对复杂心律失常的心电图判读仍需加强。\",\"period_start\":\"2026-09-01\",\"period_end\":\"2026-09-30\"}")"

echo "===== 9. 评价 ====="
R=$(curl -s --noproxy "*" -X POST "$BASE/evaluation-templates" -H "$HT" -H "$J" -H "$AJ" -d '{"name":"实习生对带教老师评教表","target":"teacher","questions":[{"content":"教学态度认真负责","max_score":50},{"content":"带教内容与病例质量","max_score":50}]}'); check "建评教模板" '"id"' "$R"
EID=$(idof "$R")
Q=$(curl -s --noproxy "*" "$BASE/evaluation-templates" -H "$HA" -H "$AJ" | "$PHP" -r "\$d=json_decode(file_get_contents('php://stdin')); foreach(\$d->list as \$t){ if(\$t->id==$EID){ echo \$t->questions[0]->id.' '.\$t->questions[1]->id; break; } }")
Q1=$(echo $Q | cut -d' ' -f1); Q2=$(echo $Q | cut -d' ' -f2)
check "张明提交评教（95 分）" '"total_score":95' "$(curl -s --noproxy "*" -X POST "$BASE/evaluations" -H "$HS" -H "$J" -H "$AJ" -d "{\"template_id\":$EID,\"target_user_id\":$T1,\"period\":\"$TODAY\",\"answers\":[{\"question_id\":$Q1,\"score\":48},{\"question_id\":$Q2,\"score\":47}]}")"

echo "===== 10. 公告 ====="
check "发布轮转通知" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/notices" -H "$HA" -H "$J" -H "$AJ" -d '{"title":"2026 年 11 月轮转科室调整通知","content":"按轮转计划，11 月 1 日起心内科实习生转入呼吸内科/骨科，请提前完成病历归档与交接。"}')"
check "发布考试通知" '"id"' "$(curl -s --noproxy "*" -X POST "$BASE/notices" -H "$HA" -H "$J" -H "$AJ" -d '{"title":"月度临床技能考核安排","content":"考核内容：心肺复苏 + 气管插管。时间：本周五 14:00。地点：临床技能中心。"}')"

echo "===== 11. 考勤打卡记录（直接写库，模拟历史打卡）====="
MYSQL=/d/phpstudy_pro/Extensions/MySQL5.7.26/bin/mysql.exe
"$MYSQL" -uroot -proot internship 2>/dev/null <<SQL
INSERT INTO checkin_day_records (user_id, work_date, check_in_at, check_out_at, status, late_minutes, remark, created_at, updated_at) VALUES
(3, '$D3', '$D3 08:02:11', '$D3 17:35:20', 'normal', 0, NULL, NOW(), NOW()),
(3, '$D2', '$D2 08:05:44', '$D2 17:32:08', 'normal', 0, NULL, NOW(), NOW()),
(3, '$D1', '$D1 07:58:30', '$D1 17:40:15', 'normal', 0, NULL, NOW(), NOW()),
(3, '$TODAY', '$TODAY 08:03:27', NULL, 'unknown', 0, NULL, NOW(), NOW()),
(5, '$D3', '$D3 08:01:02', '$D3 17:33:41', 'normal', 0, NULL, NOW(), NOW()),
(5, '$D2', '$D2 08:22:10', '$D2 17:31:55', 'late', 22, '迟到 22 分钟', NOW(), NOW()),
(5, '$D1', '$D1 08:04:19', '$D1 17:38:02', 'normal', 0, NULL, NOW(), NOW()),
(5, '$TODAY', '$TODAY 08:01:47', NULL, 'unknown', 0, NULL, NOW(), NOW()),
(6, '$D3', '$D3 08:06:33', '$D3 17:36:10', 'normal', 0, NULL, NOW(), NOW()),
(6, '$D2', '$D2 08:03:58', '$D2 17:34:44', 'normal', 0, NULL, NOW(), NOW()),
(6, '$TODAY', '$TODAY 08:10:21', NULL, 'unknown', 0, NULL, NOW(), NOW());
SQL
echo "  打卡日记录已写入（张明 3 天正常、陈雨欣含 1 次迟到、刘子豪 $D1 缺卡）"

echo
echo "===== 结果：PASS=$PASS FAIL=$FAIL ====="
echo "演示账号：admin/admin123456  teacher01/teacher123  student01/student123"
