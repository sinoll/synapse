<?php

declare(strict_types=1);

namespace frontend\models;

use common\models\User;
use common\services\MailSenderInterface;
use frontend\components\MailMessage;
use Yii;
use yii\base\Model;

/**
 * 长时间未登录账户的邮箱验证码关卡（前台 users/login 专用）。
 *
 * 挑战只挂在当前会话上，不落 user 表：这一步用户还没有身份，凭据属于"这个浏览器发起的这次登录"，
 * 而不是属于这个账户。落库会让同一账户在两处的登录互相顶掉验证码，会话结束后还会留下一条没人认领的哈希。
 */
class LoginCodeForm extends Model
{
    public string $code = '';

    private const SESSION_KEY = 'login-email-code';

    /** 两次发信的最小间隔（秒），和找回密码一致，压住邮件轰炸。 */
    private const RESEND_COOLDOWN = 60;

    /** 一个验证码允许的试错次数，超了只能重新获取。 */
    private const MAX_ATTEMPTS = 5;

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            ['code', 'required', 'message' => '请输入验证码。'],
            ['code', 'match', 'pattern' => '/^[0-9]{6}$/', 'message' => '验证码是 6 位数字。'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return ['code' => '邮箱验证码'];
    }

    /**
     * 第一步（口令刚校验通过）调用：建立挑战并把验证码发到注册邮箱。
     *
     * 这个会话上已经有没过期的挑战就直接沿用、不再发信——在两步之间来回跳转是常态，
     * 每次都重新发一封既招邮件运营商嫌，也会把用户手上刚收到的码顶掉。
     */
    public function start(User $user, bool $rememberMe, MailSenderInterface $mailSender): bool
    {
        $state = self::readState();
        if ($state !== null && $state['userId'] === (int) $user->id && $state['expiresAt'] > time()) {
            return true;
        }

        return $this->issue($user, $rememberMe, $mailSender);
    }

    /**
     * 第二步页面上的"重新发送"：受冷却期约束。
     *
     * @return bool 是否已发出新验证码
     */
    public function resend(User $user, bool $rememberMe, MailSenderInterface $mailSender): bool
    {
        $state = self::readState();
        if ($state !== null && $state['userId'] === (int) $user->id) {
            $waiting = self::RESEND_COOLDOWN - (time() - $state['sentAt']);
            if ($waiting > 0) {
                $this->addError('code', "请 {$waiting} 秒后再获取验证码。");

                return false;
            }
        }

        return $this->issue($user, $rememberMe, $mailSender);
    }

    /**
     * 校验填写的验证码。
     *
     * @return User|null 通过时返回待建立会话的账户，否则 null，原因在 [[errors]] 里
     */
    public function verifyCode(): User|null
    {
        if (!$this->validate()) {
            return null;
        }

        $state = self::readState();
        if ($state === null) {
            $this->addError('code', '登录会话已过期，请返回重新输入密码。');

            return null;
        }
        if ($state['expiresAt'] < time()) {
            self::clear();
            $this->addError('code', '验证码已过期，请重新获取。');

            return null;
        }
        if ($state['attempts'] >= self::MAX_ATTEMPTS) {
            self::clear();
            $this->addError('code', '尝试次数过多，请重新获取验证码。');

            return null;
        }

        $user = User::findOne(['id' => $state['userId'], 'status' => User::STATUS_ACTIVE]);
        if ($user === null) {
            self::clear();
            $this->addError('code', '账户状态已变化，请重新登录。');

            return null;
        }

        if (!Yii::$app->security->validatePassword($this->code, $state['hash'])) {
            self::write(['attempts' => $state['attempts'] + 1] + $state);
            $left = self::MAX_ATTEMPTS - $state['attempts'] - 1;
            $this->addError('code', $left > 0 ? "验证码不正确，还可尝试 {$left} 次。" : '验证码不正确，请重新获取。');

            return null;
        }

        self::clear();

        return $user;
    }

    /** 这个会话上正在等验证码的账户；没有挑战或已过期返回 null。 */
    public static function pendingUser(): User|null
    {
        $state = self::readState();
        if ($state === null || $state['expiresAt'] < time()) {
            return null;
        }

        return User::findOne(['id' => $state['userId'], 'status' => User::STATUS_ACTIVE]);
    }

    /** 第一步勾过的"记住我"，第二步登录时沿用。 */
    public static function pendingRememberMe(): bool
    {
        return (bool) (self::readState()['rememberMe'] ?? false);
    }

    /** 验证码剩余有效秒数，供页面提示。 */
    public static function expiresIn(): int
    {
        $state = self::readState();

        return $state === null ? 0 : max(0, $state['expiresAt'] - time());
    }

    /** 距离还能重发还差几秒，0 表示现在就能重发。 */
    public static function resendIn(): int
    {
        $state = self::readState();

        return $state === null ? 0 : max(0, self::RESEND_COOLDOWN - (time() - $state['sentAt']));
    }

    /** 验证码有效期（分钟），提示文案和邮件都取这个数。 */
    public static function lifetimeMinutes(): int
    {
        return max(1, intdiv((int) Yii::$app->params['user.loginCodeExpire'], 60));
    }

    /** 撤掉这个会话上的挑战。 */
    public static function clear(): void
    {
        Yii::$app->session->remove(self::SESSION_KEY);
    }

    /** 明文只在发信那一刻存在，会话里留的是它的 password hash。 */
    private function issue(User $user, bool $rememberMe, MailSenderInterface $mailSender): bool
    {
        $code = (string) random_int(100000, 999999);
        $lifetime = (int) Yii::$app->params['user.loginCodeExpire'];

        $sent = MailMessage::send(
            $mailSender,
            $user->email,
            '登录验证码 - ' . Yii::$app->name,
            '@frontend/mail/loginVerifyCode',
            [
                'user' => $user,
                'code' => $code,
                'minutes' => self::lifetimeMinutes(),
                'resetLink' => Yii::$app->urlManager->createAbsoluteUrl(['users/request-password-reset']),
            ],
        );
        if (!$sent) {
            // 故意不给出失败之外的出路：发不出去就进不到第二步，宁可登录不了也不绕过这一关。
            // 投递失败的真实原因由 MailMessage 记在 mail 日志分类里。
            $this->addError('code', '验证码邮件发送失败，请稍后重试。');

            return false;
        }

        self::write([
            'userId' => (int) $user->id,
            'hash' => Yii::$app->security->generatePasswordHash($code),
            'expiresAt' => time() + $lifetime,
            'sentAt' => time(),
            'attempts' => 0,
            'rememberMe' => $rememberMe,
        ]);

        return true;
    }

    private static function readState(): array|null
    {
        $state = Yii::$app->session->get(self::SESSION_KEY);
        if (
            !is_array($state)
            || !isset($state['userId'], $state['hash'], $state['expiresAt'], $state['sentAt'], $state['attempts'], $state['rememberMe'])
        ) {
            return null;
        }

        return $state;
    }

    private static function write(array $state): void
    {
        Yii::$app->session->set(self::SESSION_KEY, $state);
    }
}
