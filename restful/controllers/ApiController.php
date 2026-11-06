<?php

declare(strict_types=1);

namespace restful\controllers;

use Yii;
use restful\components\BearerAuth;
use yii\web\Controller;
use yii\web\UnauthorizedHttpException;

/**
 * API 控制器的共同底座：Bearer 鉴权 + 取请求体/当前身份。
 *
 * 动词由 urlManager 的规则限定（DELETE 只会路由到 delete 动作），
 * 所以这里不再挂 VerbFilter——两处各写一遍权限，迟早会不一致。
 */
abstract class ApiController extends Controller
{
    /**
     * 免登录也能访问的动作。AuthMethod::$optional 的语义是「先照常尝试鉴权，
     * 失败就当访客继续」，所以带合法令牌的访客在列表里仍能看到 liked、私密内容等专属字段。
     *
     * @return string[]
     */
    protected function publicActions(): array
    {
        return [];
    }

    public function behaviors(): array
    {
        return [
            'bearer' => [
                'class' => BearerAuth::class,
                'optional' => $this->publicActions(),
            ],
        ];
    }

    /** 当前身份，未登录为 null。 */
    protected function viewerId(): int|null
    {
        $id = Yii::$app->user->getId();

        return $id === null ? null : (int) $id;
    }

    /**
     * 写操作要求身份。鉴权过滤器已经拦住无令牌的请求，这里只是兜底：
     * null 变成 0 会写出 user_id=0 的孤儿内容，比报 401 难查得多。
     */
    protected function actorId(): int
    {
        $id = $this->viewerId();
        if ($id === null) {
            throw new UnauthorizedHttpException('请先调用 POST /auth/login 获取访问令牌。');
        }

        return $id;
    }

    /**
     * 请求体。application/json 由 JsonParser 解成数组，表单/查询参数同形；
     * 传了数组以外的 JSON（字符串、null）时按空请求处理，交给字段校验报错。
     *
     * @return array
     */
    protected function payload(): array
    {
        $body = Yii::$app->request->getBodyParams();

        return is_array($body) ? $body : [];
    }
}
