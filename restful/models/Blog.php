<?php

namespace app\restful\models;

use yii\base\Model;

/**
 * Blog 文章模型
 */
class Blog extends Model
{
    public $id;
    public $title;
    public $content;
    public $author;
    public $tags = [];
    public $created_at;
    public $updated_at;
    
    /**
     * 查找所有文章
     * @return array
     */
    public static function findAll()
    {
        return [
            [
                'id' => 1,
                'title' => '博客示例 1',
                'content' => '这是第一篇博客的内容...',
                'author' => 'admin',
                'tags' => ['技术', '开发'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }
    
    /**
     * 查找单篇文章
     * @param int $id
     * @return array
     */
    public static function findOne($id)
    {
        if (!$id) return [];
        
        return [
            'id' => $id,
            'title' => '博客标题',
            'content' => '这是博客的内容...',
            'author' => 'admin',
            'tags' => ['示例'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * 创建文章
     * @param array $data
     * @return bool
     */
    public static function create($data)
    {
        if (empty($data['title'])) {
            return json(['code' => 400, 'message' => '标题不能为空']);
        }
        
        $id = mt_rand(100, 999);
        
        return [
            'code' => 200,
            'message' => '创建成功',
            'data' => [
                'id' => $id,
                'title' => $data['title'],
                'content' => $data['content'] ?? '',
                'author' => $data['author'] ?? 'admin',
                'tags' => $data['tags'] ?? [],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }
    
    /**
     * 更新文章
     * @param int $id
     * @param array $data
     * @return bool|array
     */
    public static function update($id, $data)
    {
        if (!$id) {
            return json(['code' => 404, 'message' => '文章不存在']);
        }
        
        if (empty($data['title'])) {
            return json(['code' => 400, 'message' => '标题不能为空']);
        }
        
        return [
            'code' => 200,
            'message' => '更新成功',
            'data' => [
                'id' => $id,
                'title' => $data['title'] ?? '博客标题',
                'content' => $data['content'] ?? '',
                'author' => $data['author'] ?? 'admin',
                'tags' => $data['tags'] ?? [],
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }
    
    /**
     * 删除文章
     * @param int $id
     * @return bool|array
     */
    public static function delete($id)
    {
        if (!$id) {
            return json(['code' => 404, 'message' => '文章不存在']);
        }
        
        return [
            'code' => 200,
            'message' => '删除成功',
            'data' => ['id' => $id],
        ];
    }
}
