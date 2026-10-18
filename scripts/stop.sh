#!/usr/bin/env bash
# 停止实习系统的前端/后端/mock AI（保留 MySQL）
# 用法：/usr/bin/bash scripts/stop.sh
kill_port() {
  local port=$1
  local pids
  pids=$(netstat -ano | grep LISTEN | grep -E "127.0.0.1:$port\b" | awk '{print $NF}' | sort -u)
  if [ -z "$pids" ]; then echo "  $port 未运行"; return; fi
  for pid in $pids; do
    taskkill //F //PID "$pid" >/dev/null 2>&1
    echo "  已停止端口 $port（PID $pid）"
  done
}

echo "停止 mock AI (8001)…"; kill_port 8001
echo "停止前端 (5173)…";    kill_port 5173
echo "停止后端 (8000)…";    kill_port 8000
echo "完成（MySQL 保持运行）"
