<?php

declare(strict_types=1);

namespace restful\components;

use common\models\User;
use restful\services\ApiToken;
use yii\filters\auth\AuthMethod;
use yii\web\Request;
use yii\web\UnauthorizedHttpException;

/**
 * Authorization: Bearer <令牌> 鉴权。
 *
 * 只读请求头：不接 Cookie（那会把前台会话变成 API 的凭据面），也不接 ?access_token=
 * （URL 会进访问日志、代理日志和 Referer）。
 *
 * 校验通过用 setIdentity() 而不是 login()：本应用 enableSession=false，
 * setIdentity 是纯内存赋值，不会去碰会话或身份 Cookie。
 */
final class BearerAuth extends AuthMethod
{
    public function authenticate($user, $request, $response)
    {
        $token = $this->submittedToken($request);
        if ($token === null) {
            throw new UnauthorizedHttpException('缺少访问令牌，请先调用 POST /auth/login 获取 Bearer 令牌。');
        }

        $userId = ApiToken::verify($token);
        if ($userId === null) {
            throw new UnauthorizedHttpException('访问令牌无效或已过期，请重新登录。');
        }

        // findIdentity 只认 STATUS_ACTIVE，注销和未激活的账户拿不到身份。
        $identity = User::findIdentity($userId);
        if ($identity === null) {
            throw new UnauthorizedHttpException('令牌对应的账户不存在或已被禁用。');
        }

        $user->setIdentity($identity);

        return $identity;
    }

    public function challenge($response)
    {
        $response->headers->set('WWW-Authenticate', 'Bearer realm="restful-api"');
    }

    private function submittedToken(Request $request): string|null
    {
        // Apache 以 CGI/FastCGI 运行时不会把 Authorization 填进 $_SERVER，
        // 靠 web/.htaccess 的 rewrite 把它转写成 HTTP_AUTHORIZATION 环境变量。
        $header = $request->getHeaders()->get('Authorization');
        if ($header === null || $header === '') {
            return null;
        }

        return preg_match('/^\s*Bearer\s+(\S+)\s*$/i', (string) $header, $match) === 1 ? $match[1] : null;
    }
}
