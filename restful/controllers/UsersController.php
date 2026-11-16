<?php

declare(strict_types=1);

namespace restful\controllers;

use common\models\User;
use restful\components\ApiResponder;
use restful\exceptions\ValidationException;
use Yii;

final class UsersController extends ApiController
{
    public function actionSuggest(): array
    {
        $q = Yii::$app->request->get('q', '');
        if (!is_string($q)) {
            throw new ValidationException(['q' => ['q 必须是字符串。']]);
        }
        $q = mb_substr(trim($q), 0, 80);
        $query = User::find()
            ->select(['id', 'username', 'nickname', 'avatar'])
            ->where(['status' => User::STATUS_ACTIVE])
            ->andWhere(['!=', 'id', $this->actorId()])
            ->orderBy(['id' => SORT_ASC])
            ->limit(8);
        if ($q !== '') {
            $query->andWhere(['or', ['like', 'username', $q], ['like', 'nickname', $q]]);
        }

        return ApiResponder::ok(['users' => array_map(
            static fn (User $user): array => self::summary($user),
            $query->all()
        )]);
    }

    public static function summary(User|null $user): array|null
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'username' => (string) $user->username,
            'nickname' => $user->nickname,
            'name' => (string) ($user->nickname ?: $user->username),
            'avatar_url' => ApiResponder::assetUrl($user->avatar),
        ];
    }
}
