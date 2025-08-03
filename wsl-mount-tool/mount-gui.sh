#!/bin/bash

# 定義 Linux 中的掛載點
MOUNT_POINT="$HOME/writable_space"

# --- PowerShell 腳本 --- #
# 這段腳本會在 Windows 環境中執行，用來彈出 GUI
#
PS_SCRIPT="
# 載入必要的 .NET 組件
Add-Type -AssemblyName System.Windows.Forms

# 建立一個資料夾選擇對話方塊
$folderBrowser = New-Object System.Windows.Forms.FolderBrowserDialog
$folderBrowser.Description = '請選擇您想要掛載為「可寫入」的資料夾'
$folderBrowser.ShowNewFolderButton = $false

# 顯示對話方塊並檢查使用者是否點擊了「確定」
if ($folderBrowser.ShowDialog([System.Windows.Forms.Form]::new()) -eq 'OK') {
    # 將選擇的路徑輸出到標準輸出，這樣 Bash 腳本才能接收到
    return $folderBrowser.SelectedPath
} else {
    # 如果使用者取消，返回一個特定的錯誤代碼
    exit 1
}
"

# --- 主邏輯 --- #

# 顯示一個 Windows 訊息框，告知使用者工具已啟動
powershell.exe -Command "Add-Type -AssemblyName System.Windows.Forms; [System.Windows.Forms.MessageBox]::Show('準備開始掛載，請選擇資料夾。', 'WSL 掛載工具', 'OK', 'Information')" > /dev/null 2>&1

# 執行 PowerShell 腳本並捕獲使用者選擇的路徑
# powershell.exe -Command "& { $PS_SCRIPT }" 會執行腳本並等待其完成
# tr -d '\r' 是為了移除 Windows 換行符，確保路徑在 Linux 中是乾淨的
WIN_PATH=$(powershell.exe -Command "& { $PS_SCRIPT }")
EXIT_CODE=$?

# 檢查使用者是否取消了選擇
if [ $EXIT_CODE -ne 0 ]; then
    echo "使用者取消了操作。"
    powershell.exe -Command "Add-Type -AssemblyName System.Windows.Forms; [System.Windows.Forms.MessageBox]::Show('操作已取消。', 'WSL 掛載工具', 'OK', 'Warning')" > /dev/null 2>&1
    exit 1
fi

# 建立掛載點資料夾 (如果不存在)
mkdir -p "$MOUNT_POINT"

# 檢查掛載點是否已經被使用，如果是，先卸載
if mountpoint -q "$MOUNT_POINT"; then
    echo "卸載舊的掛載點: $MOUNT_POINT"
    sudo umount "$MOUNT_POINT"
fi

# 執行掛載命令
# -t drvfs: 指定檔案系統類型為 Windows
# -o metadata,uid,gid: 設定權限，讓目前使用者可以讀寫
echo "正在掛載 '$WIN_PATH' 到 $MOUNT_POINT"
sudo mount -t drvfs "$WIN_PATH" "$MOUNT_POINT" -o metadata,uid=$(id -u),gid=$(id -g)

# 檢查掛載是否成功
if mountpoint -q "$MOUNT_POINT"; then
    echo "掛載成功！"
    MSG_TEXT="成功將 '$WIN_PATH' 掛載到 $MOUNT_POINT"
    MSG_ICON="Information"
else
    echo "掛載失敗！"
    MSG_TEXT="掛載失敗！請檢查路徑或權限。
錯誤路徑: $WIN_PATH"
    MSG_ICON="Error"
fi

# 彈出 Windows 訊息框通知結果
powershell.exe -Command "Add-Type -AssemblyName System.Windows.Forms; [System.Windows.Forms.MessageBox]::Show('$MSG_TEXT', 'WSL 掛載工具', 'OK', '$MSG_ICON')" > /dev/null 2>&1

