<?php

declare(strict_types=1);

namespace restful\controllers;

use common\models\SiteSetting;
use common\models\User as UserRecord;
use restful\components\ApiResponder;
use restful\exceptions\ValidationException;
use restful\models\LoginForm;
use restful\models\PasswordResetForm;
use restful\models\PasswordResetRequestForm;
use restful\models\RegistrationCodeRequestForm;
use restful\models\RegistrationForm;
use restful\services\ApiToken;
use RuntimeException;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * 账户生命周期与令牌签发：注册、登录、找回密码、登出和当前账户。
 *
 * RESTful 登录使用无状态专用表单；注册和密码找回使用一次性邮箱验证码。
 */
final class AuthController extends ApiController
{
    protected function publicActions(): array
    {
        return [
            'register-code',
            'register',
            'login',
            'forgot-password',
            'reset-password',
        ];
    }

    public function actionRegister(): array
    {
        if ($limited = self::rateLimit('register', 10)) {
            return $limited;
        }
        if (!SiteSetting::signupEnabled()) {
            throw new ForbiddenHttpException('当前站点暂未开放注册。');
        }

        $payload = $this->payload();
        $form = new RegistrationForm();
        $form->username = self::stringField($payload, 'username');
        $form->nickname = self::stringField($payload, 'nickname', false);
        $form->email = self::stringField($payload, 'email');
        $form->code = self::codeField($payload, 'code');
        $form->password = self::stringField($payload, 'password');
        $form->confirmPassword = self::stringField($payload, 'confirmPassword');

        $user = $form->register();
        if ($user === null) {
            $errors = $form->getErrors();
            if ($errors === []) {
                throw new RuntimeException('注册账户保存失败。');
            }
            throw new ValidationException($errors, '注册信息不合法。');
        }

        return ApiResponder::ok([
            'user' => self::profile($user),
        ], '注册成功，账户已激活。');
    }

    public function actionRegisterCode(): array
    {
        if ($limited = self::rateLimit('register-code', 10)) {
            return $limited;
        }
        if (!SiteSetting::signupEnabled()) {
            throw new ForbiddenHttpException('当前站点暂未开放注册。');
        }

        $form = new RegistrationCodeRequestForm();
        $form->email = trim(self::stringField($this->payload(), 'email'));
        if (!$form->validate()) {
            throw new ValidationException($form->getErrors(), '邮箱格式不正确。');
        }

        $sent = $form->sendCode();
        if ($form->hasErrors()) {
            throw new ValidationException($form->getErrors(), '该邮箱不能用于注册。');
        }
        if ($sent === false) {
            return ApiResponder::fail(503, '验证码邮件发送失败，请稍后重试。');
        }

        return ApiResponder::ok(
            null,
            '如果该邮箱可用于注册，验证码将发送至该邮箱，请在 10 分钟内查收。',
        );
    }

    public function actionLogin(): array
    {
        if ($limited = self::rateLimit('login', 20)) {
            return $limited;
        }
        $payload = $this->payload();
        $username = trim(self::stringField($payload, 'username'));
        $password = self::stringField($payload, 'password');
        // 先把「没填」和「填错了」分开：前者是请求写错了(422)，后者是凭据不对(401)。
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
        // API 专用表单只校验凭据，不建立网站会话或写 Cookie。
        $identity = $form->validateCredentials();
        if ($identity === null) {
            $errors = $form->getErrors();
            // username 上的错误是「账户尚未激活」，直接返回；密码错误统一避免暴露账户状态。
            $message = isset($errors['password'])
                ? '用户名或密码不正确。'
                : (string) ($errors['username'][0] ?? '登录失败，请重试。');

            return ApiResponder::fail(401, $message);
        }

        $identity->updateAttributes(['last_login_at' => time()]);
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

    public function actionForgotPassword(): array
    {
        if ($limited = self::rateLimit('password-reset', 20)) {
            return $limited;
        }

        $form = new PasswordResetRequestForm();
        $form->email = trim(self::stringField($this->payload(), 'email'));
        if (!$form->validate()) {
            throw new ValidationException($form->getErrors(), '邮箱格式不正确。');
        }

        $sent = $form->sendCode();
        if ($form->hasErrors()) {
            throw new ValidationException($form->getErrors(), '该邮箱无法用于密码重置。');
        }
        if ($sent === false) {
            return ApiResponder::fail(503, '验证码邮件发送失败，请稍后重试。');
        }

        return ApiResponder::ok(null, '验证码已发送至该邮箱，请在 10 分钟内查收。');
    }

    public function actionResetPassword(): array
    {
        if ($limited = self::rateLimit('reset-password', 20)) {
            return $limited;
        }

        $payload = $this->payload();
        $form = new PasswordResetForm();
        $form->email = trim(self::stringField($payload, 'email'));
        $form->code = self::codeField($payload, 'code');
        $form->password = self::stringField($payload, 'password');
        $form->confirmPassword = self::stringField($payload, 'confirmPassword');
        if (!$form->resetPassword()) {
            $errors = $form->getErrors();
            if ($errors === []) {
                throw new RuntimeException('密码重置保存失败。');
            }
            throw new ValidationException($errors, '验证码无效或新密码不符合要求。');
        }

        return ApiResponder::ok(null, '密码已重置，请使用新密码登录。');
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

    private static function stringField(array $payload, string $field, bool $required = true): string
    {
        $value = $payload[$field] ?? null;
        if ($value === null) {
            if (!$required) {
                return '';
            }
            throw new ValidationException([$field => [$field . ' 不能为空。']]);
        }
        if (!is_string($value)) {
            throw new ValidationException([$field => [$field . ' 必须是字符串。']]);
        }
        if ($required && $value === '') {
            throw new ValidationException([$field => [$field . ' 不能为空。']]);
        }

        return $value;
    }

    /**
     * JSON clients sometimes encode the six-digit OTP as an integer, which strips leading zeroes.
     * Normalize non-negative integers back to six digits; keep string validation strict otherwise.
     */
    private static function codeField(array $payload, string $field): string
    {
        $value = $payload[$field] ?? null;
        if ($value === null) {
            throw new ValidationException([$field => [$field . ' 不能为空。']]);
        }
        if (is_int($value)) {
            if ($value < 0 || $value > 999999) {
                throw new ValidationException([$field => [$field . ' 必须是 6 位数字验证码。']]);
            }

            return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
        }
        if (!is_string($value)) {
            throw new ValidationException([$field => [$field . ' 必须是字符串或整数验证码。']]);
        }
        if ($value === '') {
            throw new ValidationException([$field => [$field . ' 不能为空。']]);
        }

        return $value;
    }

    /** 来源 IP 固定窗口配额，避免公开认证入口被暴力请求或邮件轰炸。 */
    private static function rateLimit(string $scope, int $limit): array|null
    {
        $bucket = intdiv(time(), 3600);
        $key = 'auth-rate/' . $scope . '/' . $bucket . '/'
            . hash('sha256', (string) Yii::$app->request->userIP);
        $count = (int) Yii::$app->cache->get($key) + 1;
        if (!Yii::$app->cache->set($key, $count, 3600)) {
            throw new RuntimeException('认证接口限流缓存不可用。');
        }
        if ($count <= $limit) {
            return null;
        }

        Yii::warning('认证接口请求超过来源 IP 配额：' . $scope, 'auth');

        return ApiResponder::fail(429, '操作过于频繁，请稍后再试。');
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
