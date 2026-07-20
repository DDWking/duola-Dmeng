@echo off
setlocal
set "GODOT=D:\Godot\Godot_v4.7.1-stable_win64_console.exe"
if not exist "%GODOT%" (
  echo 未找到 Godot：%GODOT%
  pause
  exit /b 1
)
echo 联机服务器启动中，端口 9001...
echo 关闭本窗口将停止联机服务。
"%GODOT%" --headless --path "%~dp0" -- --server --port=9001
pause
