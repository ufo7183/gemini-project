@echo off
SET SCRIPT_PATH=%~dp0mount-gui.sh

:: 檢查 WSL 是否正在運行，如果沒有則啟動它
wsl.exe -l -q >nul 2>&1 || (
    echo 正在啟動 WSL，請稍候...
    start /wait wsl.exe
)

:: 執行 WSL 中的腳本
:: -d Ubuntu: 指定要運行的 WSL 發行版，請根據您的實際情況修改
:: -e bash: 確保使用 bash 執行腳本
:: -c: 執行後面的命令
wsl.exe -d Ubuntu -e bash -c "chmod +x \"%SCRIPT_PATH%\" && \"%SCRIPT_PATH%\""

:: 保持視窗開啟，直到使用者按下任意鍵，以便查看輸出
:: pause
