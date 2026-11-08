<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * User model
 *
 * @property int $id
 * @property string $username
 * @property string $password_hash
 * @property string|null $password_reset_token
 * @property string $verification_token
 * @property string $email
 * @property string|null $phone
 * @property int|null $last_login_at
 * @property string|null $nickname
 * @property string|null $avatar
 * @property string|null $bio
 * @property int $must_change_password
 * @property int $is_admin
 * @property string $auth_key
 * @property int $status
 * @property int $created_at
 * @property int $updated_at
 * @property string $password write-only password
 */
class User extends ActiveRecord implements IdentityInterface
{
    public const STATUS_DELETED = 0;
    public const STATUS_INACTIVE = 9;
    public const STATUS_ACTIVE = 10;

    /** @var int|null 关注列表查询附加的关系时间，不是 user 表字段（见 UsersController 粉丝/关注列表）。 */
    public $followedAt;

    /**
     * @var string 刚签发的一次性凭据明文，只用于拼邮件链接，绝不落库；落库的是它的 sha256。
     *             不是 user 表字段。
     */
    public string $passwordResetSecret = '';

    /** @var string 同 [[passwordResetSecret]]，对应邮箱验证令牌。不是 user 表字段。 */
    public string $emailVerifySecret = '';

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%user}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            ['status', 'default', 'value' => self::STATUS_INACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_DELETED]],
            [['nickname', 'avatar', 'bio', 'phone'], 'default', 'value' => null],
            ['nickname', 'string', 'max' => 80],
            ['avatar', 'string', 'max' => 500],
            ['bio', 'string', 'max' => 500],
            ['phone', 'string', 'max' => 32],
            [['must_change_password', 'is_admin'], 'boolean'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id): User|null
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null): never
    {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername(string $username): User|null
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token 邮件链接里的明文令牌（secret.过期时间）
     * @return static|null
     */
    public static function findByPasswordResetToken(string $token): User|null
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => self::hashOneTimeToken($token),
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Finds user by verification email token
     *
     * @param string $token verify email token
     * @return static|null
     */
    public static function findByVerificationToken(string $token): User|null
    {
        if (self::oneTimeTokenExpiry($token, self::emailVerifyTokenExpire()) === null) {
            return null;
        }

        return static::find()
            ->where(['verification_token' => self::hashOneTimeToken($token)])
            ->andWhere(['<>', 'status', self::STATUS_DELETED])
            ->one();
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string|null $token password reset token
     * @return bool
     */
    public static function isPasswordResetTokenValid(string|null $token): bool
    {
        return self::oneTimeTokenExpiry($token, self::passwordResetTokenExpire()) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): int
    {
        return $this->getPrimaryKey();
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey(): string
    {
        return $this->auth_key;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey): bool
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword(string $password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token.
     *
     * @return string 邮件链接里使用的明文令牌，库里只存它的哈希
     */
    public function generatePasswordResetToken(): string
    {
        $this->passwordResetSecret = $this->issueOneTimeToken(self::passwordResetTokenExpire());
        $this->password_reset_token = self::hashOneTimeToken($this->passwordResetSecret);

        return $this->passwordResetSecret;
    }

    /**
     * Generates new token for email verification.
     *
     * @return string 邮件链接里使用的明文令牌，库里只存它的哈希
     */
    public function generateEmailVerificationToken(): string
    {
        $this->emailVerifySecret = $this->issueOneTimeToken(self::emailVerifyTokenExpire());
        $this->verification_token = self::hashOneTimeToken($this->emailVerifySecret);

        return $this->emailVerifySecret;
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken(): void
    {
        $this->password_reset_token = null;
    }

    /** 邮箱验证完成后消费掉令牌。 */
    public function removeEmailVerificationToken(): void
    {
        $this->verification_token = null;
    }

    private static function passwordResetTokenExpire(): int
    {
        return (int) Yii::$app->params['user.passwordResetTokenExpire'];
    }

    private static function emailVerifyTokenExpire(): int
    {
        return (int) Yii::$app->params['user.emailVerifyTokenExpire'];
    }

    private function issueOneTimeToken(int $lifetime): string
    {
        return Yii::$app->security->generateRandomString(32) . '.' . (time() + $lifetime);
    }

    private static function hashOneTimeToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * 校验明文一次性凭据并返回其过期时间戳，非法或过期返回 null。
     *
     * 过期时间随链接下发并被哈希绑定，伪造需要密钥；这里仍限制它不得超过配置窗口，
     * 防止链接被人为改写后长期有效。
     */
    private static function oneTimeTokenExpiry(string|null $token, int $lifetime): int|null
    {
        if ($token === null || substr_count($token, '.') !== 1) {
            return null;
        }
        [$secret, $expiresAt] = explode('.', $token, 2);
        if (strlen($secret) < 16 || !ctype_digit($expiresAt)) {
            return null;
        }
        $expiresAt = (int) $expiresAt;
        $now = time();

        return ($expiresAt > $now && $expiresAt <= $now + $lifetime) ? $expiresAt : null;
    }

    public function isAdministrator(): bool
    {
        return (bool) $this->is_admin;
    }

    /** 对外展示的名字：优先昵称，回落到用户名。 */
    public function displayName(): string
    {
        return $this->nickname ?: $this->username;
    }

    /** 脱敏邮箱，够本人认出是哪个邮箱，不够旁人补全。 */
    public function maskedEmail(): string
    {
        [$name, $domain] = array_pad(explode('@', (string) $this->email, 2), 2, '');

        return mb_substr($name, 0, 1) . '***' . ($domain === '' ? '' : '@' . $domain);
    }

    /**
     * 前台登录是否需要追加一道邮箱验证码。
     *
     * 没有登录记录的账户第一次放行：这一列是后加起来的，NULL 只代表"无从判断"，
     * 不代表"很久没来"，否则老账户会莫名被拦一次。
     */
    public function needsLoginEmailCode(): bool
    {
        return $this->last_login_at !== null
            && $this->last_login_at < time() - (int) Yii::$app->params['user.loginCodeAfterInactivity'];
    }

    /**
     * 建立会话并记下登录时间，两条路径共用：口令直接通过、以及邮箱验证码通过后的补登录。
     */
    public function signIn(bool $rememberMe): bool
    {
        if (!Yii::$app->user->login($this, $rememberMe ? 3600 * 24 * 30 : 0)) {
            return false;
        }

        $this->updateAttributes(['last_login_at' => time()]);

        return true;
    }

    public function getFollowingRelations(): ActiveQuery { return $this->hasMany(UserFollow::class, ['follower_id' => 'id']); }    public function getFollowerRelations(): ActiveQuery { return $this->hasMany(UserFollow::class, ['following_id' => 'id']); }
    public function getFollowing(): ActiveQuery { return $this->hasMany(self::class, ['id' => 'following_id'])->viaTable('{{%user_follow}}', ['follower_id' => 'id']); }
    public function getFollowers(): ActiveQuery { return $this->hasMany(self::class, ['id' => 'follower_id'])->viaTable('{{%user_follow}}', ['following_id' => 'id']); }
    public function getLikeRelations(): ActiveQuery { return $this->hasMany(PostLike::class, ['user_id' => 'id']); }
    public function getLikedPosts(): ActiveQuery { return $this->hasMany(Post::class, ['id' => 'post_id'])->viaTable('{{%post_like}}', ['user_id' => 'id']); }
    public function getFavoriteRelations(): ActiveQuery { return $this->hasMany(PostFavorite::class, ['user_id' => 'id']); }
    public function getFavoritePosts(): ActiveQuery { return $this->hasMany(Post::class, ['id' => 'post_id'])->viaTable('{{%post_favorite}}', ['user_id' => 'id']); }
}
