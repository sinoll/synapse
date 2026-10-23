<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\ChangePasswordForm;
use common\models\Post;
use common\models\VerifyPhoneForm;
use Yii;
use yii\captcha\CaptchaAction;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\Response;
use yii\db\Query;
use common\models\SharedFile;

/**
 * 公开 可访问控制器  不需要权限检查和限制
 */
class SiteController extends Controller
{
    

    /**
     * 按发布时间先后顺序，显示post表的内容.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        [$posts, $hasMore] = $this->publicFeed(1);

        return $this->render('index', [
            'posts' => $posts,
            'hasMore' => $hasMore,
            'definitions' => [
                'blog' => ['label' => '博客'],
                'images' => ['label' => '图片'],
                'moment' => ['label' => '动态'],
                'video' => ['label' => '短视频'],
                'file' => ['label' => '文件分享'],
            ],
        ]);
    }

    /** Return the next page of public content for the home feed. */
    public function actionFeed(int $page = 1): Response
    {
        $page = max(1, $page);
        [$posts, $hasMore] = $this->publicFeed($page);

        Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->asJson([
            'html' => $this->renderPartial('_feed_items', ['posts' => $posts]),
            'hasMore' => $hasMore,
        ]);
    }

    /** @return array{0: Post[], 1: bool} */
    private function publicFeed(int $page): array
    {
        $pageSize = 10;
        $userId = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;
        $query = Post::find()
            ->alias('p')
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo', 'sharedFile'])
            ->where(['p.status' => Post::STATUS_PUBLISHED]);

        if ($userId === null) {
            $query->andWhere(['p.visibility' => Post::VISIBILITY_PUBLIC]);
        } else {
            $visibleToUser = [
                'or',
                ['p.visibility' => Post::VISIBILITY_PUBLIC],
                ['p.visibility' => Post::VISIBILITY_REGISTERED_USERS],
                [
                    'and',
                    ['p.visibility' => Post::VISIBILITY_PRIVATE],
                    ['p.user_id' => $userId],
                ],
                [
                    'and',
                    ['p.visibility' => Post::VISIBILITY_SELECTED_USERS],
                    ['exists', (new Query())
                        ->select('sf.id')
                        ->from(['sf' => '{{%shared_file}}'])
                        ->innerJoin(['recipient' => '{{%shared_file_recipient}}'], 'recipient.shared_file_id = sf.id')
                        ->where('sf.post_id = p.id')
                        ->andWhere(['recipient.user_id' => $userId])
                        ->andWhere(['sf.status' => SharedFile::STATUS_ACTIVE])
                        ->andWhere(['sf.deleted_at' => null])],
                ],
            ];
            $query->andWhere($visibleToUser);
        }

        $query
            ->andWhere([
                'or',
                ['<>', 'p.type', Post::TYPE_SHARED_FILE],
                ['exists', (new Query())
                    ->select('sf.id')
                    ->from(['sf' => '{{%shared_file}}'])
                    ->where('sf.post_id = p.id')
                    ->andWhere(['sf.status' => SharedFile::STATUS_ACTIVE])
                    ->andWhere(['sf.deleted_at' => null])],
            ])
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);

        $total = (clone $query)->count();
        $posts = $query->offset(($page - 1) * $pageSize)->limit($pageSize)->all();

        return [$posts, $page * $pageSize < $total];
    }

    
    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout(): string
    {
        return $this->render('about');
    }





    

}
