@echo off
REM ============================================================================
REM     拾光 RESTful API 自检脚本启动器
REM
REM     真正的测试步骤在 test_api.ps1 里（PowerShell 才能读结构化 JSON），
REM     这个 .bat 只是给习惯双击运行的人一个入口，参数原样透传。
REM
REM     用法：test_api.bat [http://192.168.10.10]
REM ============================================================================

setlocal
set PS1=%~dp0test_api.ps1

if "%~1"=="" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%PS1%"
) else (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%PS1%" -BaseUrl %~1
)

endlocal
pause
