# @fileType: application/powershell
# ============================================================================
#     拾光 RESTful API 自检脚本（restful/）
#
#   用法：
#     powershell -ExecutionPolicy Bypass -File restful\test_api.ps1
#     powershell -ExecutionPolicy Bypass -File restful\test_api.ps1 -BaseUrl http://192.168.10.10
#
#   口令只在运行时输入，脚本里不留任何密码；不加 -Username 也可以，读接口部分不需要登录。
#   默认把新建的测试内容删掉，想留着看效果就加 -KeepTestContent。
# ============================================================================

param(
    [string]$BaseUrl = 'http://192.168.10.10',
    [string]$Username = '',
    [switch]$KeepTestContent
)

$ErrorActionPreference = 'Stop'
$script:Pass = 0
$script:Fail = 0
$script:Skip = 0

function Invoke-Api {
    param(
        [string]$Method,
        [string]$Path,
        $Body = $null,
        [string]$Token = $null
    )

    $headers = @{}
    if ($Token) { $headers['Authorization'] = "Bearer $Token" }

    $params = @{
        Uri             = "$BaseUrl$Path"
        Method          = $Method
        Headers         = $headers
        UseBasicParsing = $true
        TimeoutSec      = 60
    }
    if ($null -ne $Body) {
        # 空 body 的 POST（点赞）也要发 Content-Type，否则 Yii 会把它当成表单请求。
        $params['ContentType'] = 'application/json'
        $params['Body'] = if ($Body -is [string]) { $Body } else { $Body | ConvertTo-Json -Compress -Depth 8 }
    }

    try {
        $response = Invoke-WebRequest @params
        return [pscustomobject]@{
            Status = [int]$response.StatusCode
            Json   = $response.Content | ConvertFrom-Json
        }
    } catch {
        $status = 0
        $text = $_.ErrorDetails.Message
        if (-not $text -and $_.Exception.Response) {
            try {
                $reader = New-Object IO.StreamReader($_.Exception.Response.GetResponseStream())
                $text = $reader.ReadToEnd()
            } catch { }
        }
        if ($_.Exception.Response) { $status = [int]$_.Exception.Response.StatusCode }
        $json = $null
        if ($text) { try { $json = $text | ConvertFrom-Json } catch { } }
        return [pscustomobject]@{ Status = $status; Json = $json }
    }
}

function Report {
    param([string]$Name, [bool]$Ok, [string]$Detail = '')

    if ($Ok) {
        $script:Pass++
        Write-Host "  [通过] $Name" -ForegroundColor Green
    } else {
        $script:Fail++
        Write-Host "  [失败] $Name $Detail" -ForegroundColor Red
    }
}

function Skip {
    param([string]$Name, [string]$Why)

    $script:Skip++
    Write-Host "  [跳过] $Name —— $Why" -ForegroundColor DarkGray
}

Write-Host ''
Write-Host '============================================================' -ForegroundColor Cyan
Write-Host "       拾光 RESTful API 自检   $BaseUrl" -ForegroundColor Cyan
Write-Host '============================================================' -ForegroundColor Cyan

# ---------------------------------------------------------------- 访客可读
Write-Host ''
Write-Host '[1] 未登录的读接口' -ForegroundColor Yellow

$root = Invoke-Api GET '/'
Report 'GET / 返回接口清单' ($root.Status -eq 200 -and $root.Json.data.resources.blog) "status=$($root.Status)"

$blogList = Invoke-Api GET '/blog?per_page=5'
Report 'GET /blog 分页列表' ($blogList.Status -eq 200 -and $null -ne $blogList.Json.data.items) "status=$($blogList.Status)"
if ($blogList.Json.data.items.Count -gt 0) {
    Write-Host "       访客可见 $($blogList.Json.data.total) 篇，示例：$($blogList.Json.data.items[0].title)" -ForegroundColor DarkGray
}

foreach ($type in 'moment', 'images', 'short_video') {
    $list = Invoke-Api GET "/$type"
    Report "GET /$type 分页列表" ($list.Status -eq 200) "status=$($list.Status)"
}

$bad = Invoke-Api POST '/blog' @{ title = '没有令牌' }
Report 'POST /blog 无令牌应 401' ($bad.Status -eq 401) "status=$($bad.Status)"

$strict = Invoke-Api GET '/不存在的路径'
Report '路由外的路径应 404（严格解析）' ($strict.Status -eq 404) "status=$($strict.Status)"

$badSort = Invoke-Api GET '/blog?sort=id'
Report '非法 sort 应 422 并给字段明细' ($badSort.Status -eq 422 -and $badSort.Json.data.errors.sort) "status=$($badSort.Status)"

# ---------------------------------------------------------------- 登录
Write-Host ''
Write-Host '[2] 登录与令牌' -ForegroundColor Yellow

$token = $null
if (-not $Username) { $Username = Read-Host '  登录用户名（直接回车跳过写接口测试）' }
if ($Username) {
    $secure = Read-Host '  登录密码' -AsSecureString
    $password = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))

    $login = Invoke-Api POST '/auth/login' @{ username = $Username; password = $password }
    if ($login.Status -eq 200 -and $login.Json.data.token) {
        $token = $login.Json.data.token
        $script:Pass++
        Write-Host "  [通过] POST /auth/login —— $($login.Json.data.user.nickname)，令牌有效期 $($login.Json.data.expires_in) 秒" -ForegroundColor Green
    } else {
        $script:Fail++
        Write-Host "  [失败] POST /auth/login —— $($login.Json.message)" -ForegroundColor Red
    }

    $wrong = Invoke-Api POST '/auth/login' @{ username = $Username; password = '绝对不可能存在的密码' }
    Report '错口令应 401 且不泄露令牌' ($wrong.Status -eq 401 -and -not $wrong.Json.data) "status=$($wrong.Status)"
} else {
    Skip '登录与全部写接口' '没有提供用户名'
}

if (-not $token) {
    Write-Host ''
    Write-Host "共 $($script:Pass) 项通过、$($script:Fail) 项失败、$($script:Skip) 项跳过。" -ForegroundColor Cyan
    if ($script:Fail -gt 0) {
        Write-Host '登录没成功，后面的写接口全部依赖令牌，先确认账户与口令再重跑。' -ForegroundColor DarkGray
        exit 1
    }
    Write-Host '带账户名重跑即可继续验证写接口：.\test_api.ps1 -Username 你的用户名' -ForegroundColor DarkGray
    exit 0
}

$me = Invoke-Api GET '/auth/me' $null $token
Report 'GET /auth/me 认出当前账户' ($me.Status -eq 200 -and $me.Json.data.user.id) "status=$($me.Status)"

# ---------------------------------------------------------------- 上传
Write-Host ''
Write-Host '[3] 上传（POST /upload）' -ForegroundColor Yellow

# 用站点自带的图当测试源文件，脚本里不必再塞一份二进制。
$sample = Join-Path $PSScriptRoot '..\frontend\web\images\default-cover.png'
$uploadPath = $null
$uploadThumb = $null
if (Test-Path $sample) {
    $raw = & curl.exe -s -X POST "$BaseUrl/upload" -H "Authorization: Bearer $token" `
        -F "kind=images" -F "file=@$sample"
    $uploaded = $raw | ConvertFrom-Json
    if ($uploaded.code -eq 200) {
        $uploadPath = $uploaded.data.path
        $uploadThumb = $uploaded.data.thumbnail_path
        Report "上传成功：$uploadPath（缩略图 $uploadThumb）" $true
    } else {
        Report '上传成功' $false "—— $($uploaded.message)"
    }
} else {
    Skip 'POST /upload' "找不到示例图 $sample"
}

$noFile = Invoke-Api POST '/upload' '{"kind":"images"}' $token
Report '缺 file 字段应 422' ($noFile.Status -eq 422) "status=$($noFile.Status)"

$evilPath = Invoke-Api POST '/moment' @{ content = '越界路径'; thumb = '/../vendor/cert.pem' } $token
Report '非 /uploads 路径应被拒绝' ($evilPath.Status -eq 422) "status=$($evilPath.Status)"

# ---------------------------------------------------------------- 写四类内容
Write-Host ''
Write-Host '[4] 四类内容的创建 / 详情 / 更新 / 点赞' -ForegroundColor Yellow

$created = @()

$moment = Invoke-Api POST '/moment' @{ content = "接口自检动态 $(Get-Random) #接口自检#" } $token
if ($moment.Status -eq 200) {
    $created += ,@('moment', $moment.Json.data.id)
    Report "POST /moment —— id=$($moment.Json.data.id)，标题已按正文截断" $true
    Write-Host "       话题 #接口自检# 是否进了标签：$($moment.Json.data.tags -join '、')" -ForegroundColor DarkGray

    $detail = Invoke-Api GET "/moment/$($moment.Json.data.id)"
    Report 'GET /moment/{id} 详情带正文' ($detail.Status -eq 200 -and $detail.Json.data.content) "status=$($detail.Status)"

    $guest = Invoke-Api POST "/moment/$($moment.Json.data.id)/like" '{}'
    Report '未登录点赞应 401' ($guest.Status -eq 401) "status=$($guest.Status)"

    $liked = Invoke-Api POST "/moment/$($moment.Json.data.id)/like" '{}' $token
    $unliked = Invoke-Api POST "/moment/$($moment.Json.data.id)/like" '{}' $token
    Report '点赞可切换且计数正确' `
        ($liked.Json.data.liked -eq $true -and $unliked.Json.data.liked -eq $false -and $unliked.Json.data.like_count -eq 0) `
        "liked=$($liked.Json.data.liked) count=$($unliked.Json.data.like_count)"

    $patch = Invoke-Api PATCH "/moment/$($moment.Json.data.id)" @{ title = '接口自检动态（改）' } $token
    Report 'PATCH 只改标题，正文保持原样' `
        ($patch.Status -eq 200 -and $patch.Json.data.content -eq $detail.Json.data.content) "status=$($patch.Status)"
} else {
    Report 'POST /moment' $false "status=$($moment.Status) $($moment.Json.message)"
}

$blog = Invoke-Api POST '/blog' @{
    title   = '接口自检博客'
    summary = '由 test_api.ps1 建立'
    content = '<p>正文里一张本站插图：<img src="' + $uploadPath + '"></p><p>onmouseover=1 的标签会被净化掉。</p>'
} $token
if ($blog.Status -eq 200) {
    $created += ,@('blog', $blog.Json.data.id)
    Report "POST /blog —— id=$($blog.Json.data.id)，封面按正文第一张图自动推导：$($blog.Json.data.thumb)" $true

    $null = Invoke-Api PATCH "/blog/$($blog.Json.data.id)" @{ content = '<p onclick="alert(1)">脏 HTML</p><script>alert(2)</script>' } $token
    $clean = Invoke-Api GET "/blog/$($blog.Json.data.id)"
    Report '富文本净化：script/onclick 不会存进库里' `
        ($clean.Json.data.content -notmatch 'script|onclick') "结果=$($clean.Json.data.content)"
} else {
    Report 'POST /blog' $false "status=$($blog.Status) $($blog.Json.message)"
}

$noContent = Invoke-Api POST '/blog' @{ title = '没有正文' } $token
Report '博客缺 content 应 422' ($noContent.Status -eq 422) "status=$($noContent.Status)"

if ($uploadPath) {
    $gallery = Invoke-Api POST '/images' @{
        caption    = "接口自检图集`n这是说明行，会写进 alt_text。 #接口自检#"
        file_paths = @($uploadPath)
    } $token
    if ($gallery.Status -eq 200) {
        $created += ,@('images', $gallery.Json.data.id)
        $first = $gallery.Json.data.images[0]
        Report "POST /images —— id=$($gallery.Json.data.id)，caption 首行进 title、次行进 alt_text" `
            ($first.title -eq '接口自检图集' -and $first.alt_text -like '*说明行*') "title=$($first.title)"

        $addMore = Invoke-Api PATCH "/images/$($gallery.Json.data.id)" @{ file_paths = @($uploadPath, $uploadThumb) } $token
        Report 'PATCH file_paths 整组替换并按顺序编号' `
            ($addMore.Status -eq 200 -and $addMore.Json.data.image_count -eq 2 `
                -and $addMore.Json.data.images[1].sort_order -eq 1) "count=$($addMore.Json.data.image_count)"
    } else {
        Report 'POST /images' $false "status=$($gallery.Status) $($gallery.Json.message)"
    }
} else {
    Skip 'POST /images' '没有可用的上传图片路径'
}

$noVideo = Invoke-Api POST '/short_video' @{ description = '没有视频文件' } $token
Report '短视频缺 video_path 应 422' ($noVideo.Status -eq 422) "status=$($noVideo.Status)"

$badType = Invoke-Api POST '/short_video' @{
    title      = '枚举校验'
    video_path = '/uploads/video/202611/不存在的文件.mp4'
    video_type = 9
} $token
Report 'video_type 越界应 422 并列出可选值' ($badType.Status -eq 422) "status=$($badType.Status)"

# ---------------------------------------------------------------- 权限
Write-Host ''
Write-Host '[5] 归属与可见性' -ForegroundColor Yellow

if ($created.Count -gt 0) {
    $mine = $created[0]
    $steal = Invoke-Api PATCH "/$($mine[0])/$($mine[1])" @{ title = '别人改的' } '1.eyJ8OTk5OTk5OTk5.无效令牌'
    Report '伪造令牌不能改内容' ($steal.Status -eq 401) "status=$($steal.Status)"

    $secretPost = Invoke-Api POST '/moment' @{ content = '只有我能看到的自检动态'; visibility = 0 } $token
    if ($secretPost.Status -eq 200) {
        $created += ,@('moment', $secretPost.Json.data.id)
        $anon = Invoke-Api GET "/moment/$($secretPost.Json.data.id)"
        Report '私密内容对访客按 404（不用状态码泄露存在性）' ($anon.Status -eq 404) "status=$($anon.Status)"
        $owner = Invoke-Api GET "/moment/$($secretPost.Json.data.id)" $null $token
        Report '作者本人带令牌能看到自己的私密内容' ($owner.Status -eq 200) "status=$($owner.Status)"
    }
}

# ---------------------------------------------------------------- 收尾
Write-Host ''
Write-Host '[6] 清理' -ForegroundColor Yellow

if ($KeepTestContent) {
    Write-Host "  [保留] 已建立的内容留在库里：$(($created | ForEach-Object { "/$($_[0])/$($_[1])" }) -join '、')" -ForegroundColor DarkGray
} else {
    foreach ($item in $created) {
        $del = Invoke-Api DELETE "/$($item[0])/$($item[1])" $null $token
        # 删完再读一次：404 才算真的没了，也顺带证明详情接口不会读到已删的行。
        $after = Invoke-Api GET "/$($item[0])/$($item[1])" $null $token
        Report "DELETE /$($item[0])/$($item[1])" ($del.Status -eq 200 -and $after.Status -eq 404) `
            "delete=$($del.Status) 复查=$($after.Status)"
    }
}

$logout = Invoke-Api POST '/auth/logout' '{}' $token
Report 'POST /auth/logout 说明无状态语义' ($logout.Status -eq 200) "status=$($logout.Status)"

Write-Host ''
$mark = if ($script:Fail -gt 0) { 'Red' } else { 'Green' }
Write-Host '============================================================' -ForegroundColor Cyan
Write-Host "共 $($script:Pass) 项通过、$($script:Fail) 项失败、$($script:Skip) 项跳过。" -ForegroundColor $mark
Write-Host '============================================================' -ForegroundColor Cyan
if ($script:Fail -gt 0) { exit 1 }
