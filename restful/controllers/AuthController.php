<?php

declare(strict_types=1);

namespace restful\controllers;

use common\models\LoginForm;
use common\models\User as UserRecord;
use restful\components\ApiResponder;
use restful\exceptions\ValidationException;
use restful\services\ApiToken;
use Yii;
use yii\web\UnauthorizedHttpException;

/**
 * 令牌签发：POST /auth/login、/auth/logout、GET /auth/me。
 *
 * 口令校验完全复用 common\models\LoginForm，包括「账户未激活」的提示口径。
 * 前台那道「久未登录要先过邮箱验证码」的关卡这里刻意不开启：验证码邮件里的链接指向网站，
 * 无状态的接口客户端拿不到也确认不了会话（见 LoginForm::$emailCodeEnabled 的默认值）。
 */
final class AuthController extends ApiController
{
    protected function publicActions(): array
    {
        return ['login'];
    }

    public function actionLogin(): array
    {
        $payload = $this->payload();
        $username = self::credential($payload, 'username');
        $password = self::credential($payload, 'password');
        // 先把「没填」和「填错了」分开：前者是请求写错了(422)，后者是凭据不对(401)，
        // 而且 LoginForm 的必填文案是英文的，接口不该照抄。
        $missing = [];
        foreach (['username' => $username, 'password' => $password] as $field => $value) {
            if ($value === '') {
                $missing[$field] = [$field . ' 不能为空。'];
            }
        }
        if ($missing !== []) {
            throw new ValidationException($missing, '登录参数不完整。');
        }

        $form = new LoginForm();
        $form->username = $username;
        $form->password = $password;
        // 不读 remember_me：接口不写会话，「记住我」在这里没有可延长的东西。
        if (!$form->validate()) {
            $errors = $form->getErrors();
            // 口令错误的文案在 LoginForm 里是英文的，接口统一给中文；
            // username 上的错误是「账户尚未激活」，本来就是给人看的中文，原样返回。
            $message = isset($errors['password'])
                ? '用户名或密码不正确。'
                : (string) ($errors['username'][0] ?? '登录失败，请重试。');

            return ApiResponder::fail(401, $message);
        }
        if (!$form->login()) {
            // 走到这里口令已经校验通过，唯一能让它失败的是被关掉的邮箱验证码关卡；
            // 留着这层兜底，免得将来打开开关时接口直接吐出无符号的令牌。
            throw new UnauthorizedHttpException('登录未完成，请改用网站登录。');
        }

        $identity = Yii::$app->user->getIdentity();
        if (!$identity instanceof UserRecord) {
            throw new UnauthorizedHttpException('登录状态异常，请重试。');
        }
        if ((int) $identity->must_change_password === 1) {
            return ApiResponder::fail(403, '该账户还需要先修改初始密码，请到网站的「修改密码」页面（/users/change-password）完成后再调用本接口。');
        }

        $lifetime = ApiToken::lifetime();

        return ApiResponder::ok([
            'token' => ApiToken::issue((int) $identity->id),
            'token_type' => 'Bearer',
            'expires_in' => $lifetime,
            'expires_at' => ApiResponder::timestamp(time() + $lifetime),
            'user' => self::profile($identity),
        ], '登录成功。');
    }

    /**
     * 无状态令牌没有服务端会话可销毁，所以这个接口只是给客户端一个明确的收尾点。
     * 要立即作废全部令牌：删除 site_setting 里的 api_token_secret。
     */
    public function actionLogout(): array
    {
        return ApiResponder::ok(null, '已登出。接口是无状态的，令牌在有效期内仍可继续使用；如需立即失效，请重置站点设置里的 api_token_secret。');
    }

    public function actionMe(): array
    {
        $identity = Yii::$app->user->getIdentity();
        if (!$identity instanceof UserRecord) {
            throw new UnauthorizedHttpException('访问令牌无效或已过期，请重新登录。');
        }

        return ApiResponder::ok(['user' => self::profile($identity)]);
    }

    private static function credential(array $payload, string $field): string
    {
        $value = $payload[$field] ?? null;
        if ($value !== null && !is_string($value) && !is_numeric($value)) {
            throw new ValidationException([$field => [$field . ' 必须是字符串。']]);
        }

        return trim((string) $value);
    }

    /** @return array 对外可见的账户字段：不含 password_hash、auth_key 与各类验证码哈希。 */
    private static function profile(UserRecord $user): array
    {
        return [
            'id' => (int) $user->id,
            'username' => $user->username,
            'nickname' => $user->nickname,
            'email' => $user->email,
            'bio' => $user->bio,
            'avatar' => $user->avatar,
            'avatar_url' => ApiResponder::assetUrl($user->avatar),
            'is_admin' => (bool) $user->is_admin,
            'status' => (int) $user->status,
            'last_login_at' => ApiResponder::timestamp((int) $user->last_login_at),
        ];
    }
}
