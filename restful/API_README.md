# Restful API 接口文档

## 概述

本目录包含 Yii2 框架的 RESTful API 接口，目前提供用户认证相关的功能。

## 接口说明

### 1. 登录接口

**路径**: `POST /api/restful/auth/login`

**请求参数**:
```json
{
    "username": "用户名",
    "password": "密码"
}
```

**响应示例**:

成功时:
```json
{
    "code": 200,
    "message": "登录成功",
    "data": {
        "token": "auth_token_admin_1761408000",
        "expires_in": 604800
    }
}
```

失败时 (用户名或密码错误):
```json
{
    "code": 401,
    "message": "用户名或密码错误"
}
```

---

### 2. 登出接口

**路径**: `POST /api/restful/auth/logout`

**请求头** (可选，使用 Token 登录时需要):
```
Authorization: Bearer auth_token_xxx
```

**响应示例**:

成功时:
```json
{
    "code": 200,
    "message": "登出成功"
}
```

未登录时:
```json
{
    "code": 401,
    "message": "用户未登录，无法登出"
}
```

---

## 配置说明

### 启用 API 路由

在 `yii-config` 中需要配置如下 URL 路由:

```php
'urlManager' => [
    'enableStrictMode' => false,
    'rules' => [
        [
            'class' => 'yii\web\GroupUrlRule',
            'prefix' => '/api/restful/',
            'callback' => function ($route) {
                Yii::createInstance([
                    'class' => \yii\web\UrlRule::class,
                    'cacheKeyPrefix' => 'api-restful-',
                    'pattern' => '<c:[\w]+>/<a:[^/]+>',
                    'classMap' => [
                        'auth/login' => 'auth/login',
                        'auth/logout' => 'auth/logout',
                    ],
                ]);
            },
        ],
    ],
],
```

### 或在 urlmanager 中添加:

```php
'urlManager' => [
    'rules' => [
        'auth/login' => '/restful/auth/login',
        'auth/logout' => '/restful/auth/logout',
    ],
],
```

---

## 后续扩展建议

1. **Token 认证**: 使用 JWT (json-web-token) 替代简单的字符串 token
2. **密码安全**: 使用 bcrypt 对密码进行加密存储
3. **数据库集成**: 将用户信息从数据库查询，而不是硬编码
4. **Rate Limiting**: 添加登录失败次数限制防止暴力破解
5. **刷新 Token**: 实现 refresh token 机制延长登录有效期
