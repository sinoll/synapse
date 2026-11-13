<?php

declare(strict_types=1);

namespace restful\controllers;

use restful\components\ApiResponder;
use restful\models\ApiResource;
use restful\models\Blog;
use restful\models\Images;
use restful\models\Moment;
use restful\models\ShortVideo;
use restful\services\ApiToken;

/** GET / —— 接口能力清单。 */
final class SiteController extends ApiController
{
    /** @var class-string<ApiResource>[] 四类内容，顺序与站点导航一致。 */
    private const RESOURCES = [Blog::class, Moment::class, Images::class, ShortVideo::class];

    protected function publicActions(): array
    {
        return ['index'];
    }

    public function actionIndex(): array
    {
        $resources = [];
        foreach (self::RESOURCES as $resource) {
            // post.type 的取值本来就是路由里的路径段，不用另外维护一张对照表。
            $path = $resource::postType();
            $resources[$path] = [
                'label' => $resource::label(),
                'list' => "GET /$path",
                'detail' => "GET /$path/{id}",
                'create' => "POST /$path",
                'update' => "PUT|PATCH /$path/{id}",
                'delete' => "DELETE /$path/{id}",
                'like' => "POST /$path/{id}/like",
                'write_fields' => $resource::writableFields(),
            ];
        }

        return ApiResponder::ok([
            'auth' => [
                'register_code' => 'POST /auth/register/code，提交 email 后发送注册验证码',
                'register' => 'POST /auth/register，提交 username、email、code、password、confirmPassword'
                    . '，可选 nickname；验证码通过后直接激活账户',
                'login' => 'POST /auth/login，body: {"username":"...","password":"..."}',
                'forgot_password' => 'POST /auth/forgot-password，body: {"email":"..."}；发送密码重置验证码',
                'reset_password' => 'POST /auth/reset-password，提交 email、code、password、confirmPassword',
                'header' => 'Authorization: Bearer <token>',
                'expires_in' => ApiToken::lifetime(),
                'stateless' => '令牌自包含：没有会话，服务端不保存也不逐个吊销。',
            ],
            'media' => [
                'upload' => 'POST /upload（multipart/form-data：file + kind，kind ∈ blog、moment、images、cate、video）',
                'usage' => '把返回的 path 填进内容接口的 thumb / file_paths / video_path / thumbnail_path 字段。',
            ],
            'list_query' => ['page', 'per_page', 'sort（created_at|updated_at|like_count|views）', 'order（asc|desc）', 'user_id', 'q', 'tag'],
            'resources' => $resources,
            'categories' => [
                'list' => 'GET /cate',
                'detail' => 'GET /cate/{id}',
                'fields' => ['id', 'name', 'slug', 'description', 'thumb', 'thumb_url', 'created_at', 'updated_at'],
                'read_only' => '分类由站点管理后台维护；博客发布时通过 cate_id 关联。',
            ],
            'rules' => [
                '写操作需要令牌；列表与详情游客可读，带令牌时能看到自己可见的私密与草稿内容，并附带 liked 状态。',
                '只有作者本人能更新或删除，管理员不豁免。',
                '更新是部分更新：只改请求体里出现的字段。',
                '详情接口不累加 views，访问量仍只由网站浏览产生。',
                '删除内容会连带删除其独占的封面/视频文件；正文内联图片只在网站上删除时清理。',
            ],
        ]);
    }
}
