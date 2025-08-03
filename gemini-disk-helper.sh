#!/bin/bash

echo "🧠 Gemini CLI - 整理助手 啟動中..."
echo "🔧 請輸入你想讓 Gemini 存取的 D 槽整理資料夾（可寫）:"
read -e -p "📝 整理資料夾（例如 D:/整理區）: " WRITE_DIR

echo "🔒 請輸入你不希望 Gemini 存取的相簿資料夾（會被排除）:"
read -e -p "🛑 相簿資料夾（例如 D:/我的照片）: " EXCLUDE_DIR

# 安全性與格式確認
if [ ! -d "/mnt/d" ]; then
  echo "❌ 無法找到 D 槽，請確認 WSL 已正確掛載 Windows 磁碟。"
  exit 1
fi

# 產生 Docker 掛載指令
READ_MOUNT="-v /mnt/d:/mnt/d:ro"
WRITE_MOUNT="-v /mnt/d/$(basename "$WRITE_DIR"):/mnt/d/$(basename "$WRITE_DIR"):rw"
EXCLUDE_FILTER=""

if [ -n "$EXCLUDE_DIR" ]; then
  EXCLUDE_FILTER="--mount type=volume,source=null,target=/mnt/d/$(basename "$EXCLUDE_DIR")"
fi

echo "✅ 正在啟動 Gemini CLI..."
docker run -it --rm \
  $READ_MOUNT \
  $WRITE_MOUNT \
  $EXCLUDE_FILTER \
  -e GEMINI_API_KEY=$GEMINI_API_KEY \
  -w /mnt/d/$(basename "$WRITE_DIR") \
  ghcr.io/google/gemini-cli

echo "👋 Gemini 整理助手已關閉。"
