#!/usr/bin/env bash
# 一键启动实习系统（MySQL 检查 + 后端 8000 + 前端 5173 + mock AI 8001）
# 用法：/usr/bin/bash scripts/start.sh      （在项目根目录执行）
# 用 stop.sh 停止。
ROOT="/d/实习系统"
PHP="/d/phpstudy_pro/Extensions/php/php8.2.9nts/php.exe"
NODE="C:/Users/yangweihua/.workbuddy/binaries/node/versions/22.22.2-3/node.exe"

port_up() { netstat -ano | grep LISTEN | grep -qE "127.0.0.1:$1\b"; }

echo "===== 1. MySQL ====="
if ! port_up 3306; then
  echo "MySQL 未运行，正在启动（phpStudy）…"
  ( "$ROOT/../phpstudy_pro/Extensions/MySQL5.7.26/bin/mysqld.exe" \
      --defaults-file="D:/phpstudy_pro/Extensions/MySQL5.7.26/my.ini" >/dev/null 2>&1 & )
  for i in $(seq 1 20); do sleep 1; port_up 3306 && break; done
fi
if port_up 3306; then echo "  MySQL 已就绪 (3306)"; else echo "  ⚠ MySQL 起不来，请从 phpStudy 面板手动启动"; fi

echo "===== 2. 后端 Laravel (8000) ====="
if port_up 8000; then
  echo "  已在运行，跳过"
else
  ( cd "$ROOT/internship-server" && "$PHP" artisan serve --host=127.0.0.1 --port=8000 >/tmp/backend.log 2>&1 & )
  for i in $(seq 1 20); do sleep 1; port_up 8000 && break; done
  port_up 8000 && echo "  已启动 (8000)" || { echo "  ✗ 启动失败，查看 /tmp/backend.log"; }
fi

echo "===== 3. 前端 Vite (5173) ====="
if port_up 5173; then
  echo "  已在运行，跳过"
else
  ( cd "$ROOT/internship-web" && npm run dev >/tmp/frontend.log 2>&1 & )
  for i in $(seq 1 25); do sleep 1; port_up 5173 && break; done
  port_up 5173 && echo "  已启动 (5173)" || { echo "  ✗ 启动失败，查看 /tmp/frontend.log"; }
fi

echo "===== 4. mock AI 助手 (8001，可选) ====="
if port_up 8001; then
  echo "  已在运行，跳过"
else
  ( cd "$ROOT" && "$NODE" scripts/mock-ai.js >/tmp/mock-ai.log 2>&1 & )
  for i in $(seq 1 10); do sleep 1; port_up 8001 && break; done
  port_up 8001 && echo "  已启动 (8001)" || echo "  ⚠ mock AI 未启动（不影响主功能）"
fi

echo ""
echo "===== 完成 ====="
echo "系统地址：http://127.0.0.1:5173"
echo "后端接口：http://127.0.0.1:8000/api/v1"
echo "mock AI ：http://127.0.0.1:8001（真实 AI 助手上线后停掉它即可）"
echo "账号：admin/admin123456、teacher01/teacher123、student01/student123"
