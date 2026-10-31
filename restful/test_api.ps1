<!--- @fileType: application/powershell --->
# ============================================================================
#     RESTful API 接口测试脚本 - Yii2 Synapse
# ============================================================================

$ErrorActionPreference = "Stop"

# 配置
$baseUrl = "http://localhost/api/restful"
$username = "default"
$password = ""
$outputDir = "C:\Temp\restful-api-test"

# 创建输出目录
if (!(Test-Path $outputDir)) {
    New-Item -ItemType Directory -Force -Path $outputDir | Out-Null
}

Write-Host "============================================================"
Write-Host "       RESTful API 接口测试套件 - Yii2 Synapse"
Write-Host "============================================================"
Write-Host ""
Write-Host "基础 URL: $baseUrl"
Write-Host "测试用户名：$username"
Write-Host "测试密码：隐藏（安全）"
Write-Host ""

# ============================================================================
# 功能 1: 登录接口测试
# ============================================================================
Write-Host "============================================================"
Write-Host "[测试 1] 用户登录接口 - POST /auth/login"
Write-Host "============================================================"

try {
    $body = @{
        username = $username
        password = $password
    } | ConvertTo-Json -Compress
    
    $response = Invoke-RestMethod -Uri "$baseUrl/auth/login" -Method Post `
                                  -Body $body `
                                  -ContentType "application/json" `
                                  -TimeoutSec 30
    
    $resultFilePath = Join-Path $outputDir "login_response.json"
    $response | ConvertTo-Json | Out-File -FilePath $resultFilePath
    $resultPath = $resultFilePath
    
    Write-Host ""
    Write-Host "[登录响应]:" 
    Write-Host "------------------------------------------------------------"
    Write-Host "$response" | Format-List
    Write-Host "------------------------------------------------------------"
    
    if ($response.code -eq 200) {
        Write-Host ""
        Write-Host "✓ [成功] 登录接口测试通过!"
        Write-Host "   Token: $($response.data.token)"
        $loginToken = $response.data.token
    } else {
        Write-Host ""
        Write-Host "✗ [失败] 登录响应码：$($response.code) - $($response.message)"
    }
} catch {
    Write-Host ""
    Write-Host "[错误信息]:" 
    Write-Host "------------------------------------------------------------"
    Write-Host $_.Exception.Message
    Write-Host "------------------------------------------------------------"
}

# ============================================================================
# 功能 2: 登出接口测试（需要先登录获取 token）
# ============================================================================
if ($loginToken) {
    Write-Host ""
    Write-Host "============================================================"
    Write-Host "[测试 2] 用户登出接口 - POST /auth/logout"
    Write-Host "============================================================"
    
    try {
        $headers = @{
            "Authorization" = "Bearer $loginToken"
        }
        
        $response = Invoke-RestMethod -Uri "$baseUrl/auth/logout" -Method Post `
                                      -Headers $headers `
                                      -ContentType "application/json" `
                                      -TimeoutSec 30
    
        Write-Host ""
        Write-Host "[登出响应]:" 
        Write-Host "------------------------------------------------------------"
        Write-Host "$response" | Format-List
        Write-Host "------------------------------------------------------------"
        
        if ($response.code -eq 200) {
            Write-Host ""
            Write-Host "✓ [成功] 登出接口测试通过!"
        } else {
            Write-Host ""
            Write-Host "✗ [失败] 登出响应码：$($response.code) - $($response.message)"
        }
    } catch {
        Write-Host ""
        Write-Host "[错误信息]:" 
        Write-Host "------------------------------------------------------------"
        Write-Host $_.Exception.Message
        Write-Host "------------------------------------------------------------"
    }
} else {
    Write-Host ""
    Write-Host "⚠ [跳过] 登出测试 - 登录失败，无法进行登出测试"
}

# ============================================================================
# 功能 3: 博客接口测试
# ============================================================================
Write-Host ""
Write-Host "============================================================"
Write-Host "[测试 3] 博客 (Blog) CRUD 接口测试"
Write-Host "============================================================"

$blogHeaders = @{
    "Accept" = "application/json"
}

try {
    # 3.1 获取所有博客列表
    Write-Host ""
    Write-Host "[3.1] GET /blog - 获取所有博客列表"
    $response = Invoke-RestMethod -Uri "$baseUrl/blog" -Method Get `
                                  -Headers $blogHeaders `
                                  -TimeoutSec 30
    
    Write-Host "------------------------------------------------------------"
    Write-Host "响应数据:" 
    Write-Host ($response | ConvertTo-Json)
    Write-Host "------------------------------------------------------------"
    
    # 保存结果
    (Get-Content $resultPath, ($response | ConvertTo-Json)) | Set-Content $resultPath -Append
    
    if ($response.data.Count -gt 0) {
        Write-Host "✓ [成功] 获取博客列表成功，共 $($response.data.Count) 篇"
    } else {
        Write-Host "⚠ [提示] 博客列表为空"
    }
    
} catch {
    Write-Host ""
    Write-Host "[3.1 错误]" 
    Write-Host "------------------------------------------------------------"
    Write-Host $_.Exception.Message
    Write-Host "------------------------------------------------------------"
}

# ============================================================================
# 功能 4: 动态接口测试
# ============================================================================
Write-Host ""
Write-Host "============================================================"
Write-Host "[测试 4] 动态 (Moment) CRUD 接口测试"
Write-Host "============================================================"

try {
    # 4.1 获取所有动态列表
    Write-Host ""
    Write-Host "[4.1] GET /moment - 获取所有动态"
    $response = Invoke-RestMethod -Uri "$baseUrl/moment" -Method Get `
                                  -Headers $blogHeaders `
                                  -TimeoutSec 30
    
    Write-Host "------------------------------------------------------------"
    Write-Host "响应数据:" 
    Write-Host ($response | ConvertTo-Json)
    Write-Host "------------------------------------------------------------"
    
} catch {
    Write-Host ""
    Write-Host "[错误] $_.Exception.Message"
}

# ============================================================================
# 功能 5: 短视频接口测试
# ============================================================================
Write-Host ""
Write-Host "============================================================"
Write-Host "[测试 5] 短视频 (ShortVideo) CRUD 接口测试"
Write-Host "============================================================"

try {
    # 5.1 获取所有视频列表
    Write-Host ""
    Write-Host "[5.1] GET /short_video - 获取所有视频"
    $response = Invoke-RestMethod -Uri "$baseUrl/short_video" -Method Get `
                                  -Headers $blogHeaders `
                                  -TimeoutSec 30
    
    Write-Host "------------------------------------------------------------"
    Write-Host "响应数据:" 
    Write-Host ($response | ConvertTo-Json)
    Write-Host "------------------------------------------------------------"
    
} catch {
    Write-Host ""
    Write-Host "[错误] $_.Exception.Message"
}

# ============================================================================
# 功能 6: 图片接口测试
# ============================================================================
Write-Host ""
Write-Host "============================================================"
Write-Host "[测试 6] 图片 (Images) CRUD 接口测试"
Write-Host "============================================================"

try {
    # 6.1 获取所有图片列表
    Write-Host ""
    Write-Host "[6.1] GET /images - 获取所有图片"
    $response = Invoke-RestMethod -Uri "$baseUrl/images" -Method Get `
                                  -Headers $blogHeaders `
                                  -TimeoutSec 30
    
    Write-Host "------------------------------------------------------------"
    Write-Host "响应数据:" 
    Write-Host ($response | ConvertTo-Json)
    Write-Host "------------------------------------------------------------"
    
} catch {
    Write-Host ""
    Write-Host "[错误] $_.Exception.Message"
}

# ============================================================================
# 测试报告生成
# ============================================================================
Write-Host ""
Write-Host "============================================================"
Write-Host "         RESTful API 接口测试 - 总结报告"
Write-Host "============================================================"
Write-Host ""

$reportPath = Join-Path $outputDir "test_report.txt"

@"
╔══════════════════════════════════════════════════════════╗
║           RESTful API 接口测试套件 - 测试报告             ║
╚══════════════════════════════════════════════════════════╝

基础 URL: $baseUrl
测试时间：$(Get-Date -Format "yyyy-MM-dd HH:mm:ss")
测试账号：$username

测试结果概要:
-------------
登录接口：      待服务器启动后执行
登出接口：      待服务器启动后执行
博客列表：      待服务器启动后执行
动态列表：      待服务器启动后执行
短视频列表：    待服务器启动后执行
图片列表：      待服务器启动后执行

详细结果:
---------
详见各测试模块的输出

提示:
-----
1. 确保 Web 服务器已启动 (php -S localhost:8080 或 apache/nginx)
2. 确保 Yii2 应用配置正确，路由规则已启用
3. 首次运行需先通过登录接口获取 token

输出目录：$outputDir
"@ | Out-File -FilePath $reportPath -Encoding UTF8

Write-Host ""
Write-Host "测试报告已保存到：" $reportPath
Write-Host "详细结果请查看各测试模块的输出"

# 保存 JSON 响应
@"
{
    "url": "$baseUrl",
    "username": "$username",
    "password": "****** (安全隐藏)",
    "testTime": "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')",
    "status": "completed"
}
"@ | Out-File -FilePath (Join-Path $outputDir "config.json") -Encoding UTF8

Write-Host ""
Write-Host "============================================================"
Write-Host "测试脚本执行完成!"
Write-Host "============================================================"
Write-Host ""
Write-Host "输出目录：" $outputDir
Write-Host ""
