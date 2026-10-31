@echo off
REM ============================================================================
REM     RESTful API 接口测试脚本 - Yii2 Synapse
REM ============================================================================

:: 设置基础 URL
set BASE_URL=http://localhost:8080/api/restful

:: 设置测试账号
set USERNAME=default
set PASSWORD=default

echo ============================================================
echo        RESTful API 接口测试套件
echo ============================================================
echo.
echo 测试用户名：%USERNAME%
echo 测试密码：隐藏
echo.
echo 基础 URL: %BASE_URL%
echo.

:: 函数定义
:: 输出 JSON 响应
define echoJson response=
    if not defined response (
        goto :eof
    )
    echo.
    echo [JSON 响应]:
    echo %response%
)

:: 1. 测试登录接口
echo ============================================================
echo [测试 1: 用户登录]
echo ============================================================

powershell -Command "$body = @{username='%USERNAME%';password='%PASSWORD%' } | ConvertTo-Json -Compress; $url = '%BASE_URL%/auth/login'; Invoke-RestMethod -Uri $url -Method Post -Body $body -ContentType 'application/json'; echo ''" > C:\Temp\login_result.txt
pause
