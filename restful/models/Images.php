<?php

namespace app\restful\models;

use yii\base\Model;

/**
 * Images (图片) 模型
 */
class Images extends Model
{
    public $id;
    public $title;
    public $description;
    public $image_url;
    public $alt_text;
    public $user_id;
    public $tags = [];
    public $width;
    public $height;
    public $file_size;
    public $mime_type;
    public $likes = [];
    public $views;
    public $created_at;
    public $updated_at;
    
    /**
     * 查找所有图片
     * @param int $userId 可选：筛选特定用户的图片
     * @return array
     */
    public static function findAll($userId = null)
    {
        $images = [
            [
                'id' => 1,
                'title' => '美丽风景',
                'description' => '今天的晚霞真美',
                'image_url' => 'https://example.com/image1.jpg',
                'alt_text' => '美丽的晚霞景色',
                'user_id' => 1,
                'tags' => ['风景', '日落'],
                'width' => 1920,
                'height' => 1080,
                'file_size' => 256000,
                'mime_type' => 'image/jpeg',
                'likes' => [2, 3],
                'views' => 500,
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
        
        return $userId ? array_filter($images, fn($i) => $i['user_id'] == $userId) : $images;
    }
    
    /**
     * 查找单个图片
     * @param int $id
     * @return array
     */
    public static function findOne($id)
    {
        if (!$id) return [];
        
        return [
            'id' => $id,
            'title' => '图片标题',
            'description' => '这是图片的描述...',
            'image_url' => 'https://example.com/image.jpg',
            'alt_text' => '图片描述文本',
            'user_id' => 1,
            'tags' => [],
            'width' => 800,
            'height' => 600,
            'file_size' => 102400,
            'mime_type' => 'image/jpeg',
            'likes' => [],
            'views' => 100,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * 创建图片
     * @param array $data
     * @return bool|array
     */
    public static function create($data)
    {
        if (empty($data['image_url'])) {
            return json(['code' => 400, 'message' => '图片 URL 不能为空']);
        }
        
        $id = mt_rand(100, 999);
        
        return [
            'code' => 200,
            'message' => '上传成功',
            'data' => [
                'id' => $id,
                'title' => $data['title'] ?? '',
                'description' => $data['description'] ?? '',
                'image_url' => $data['image_url'],
                'alt_text' => $data['alt_text'] ?? '',
                'user_id' => $data['user_id'] ?? 1,
                'tags' => $data['tags'] ?? [],
                'width' => $data['width'] ?? 0,
                'height' => $data['height'] ?? 0,
                'file_size' => $data['file_size'] ?? 0,
                'mime_type' => $data['mime_type'] ?? 'image/jpeg',
                'likes' => [],
                'views' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }
    
    /**
     * 更新图片
     * @param int $id
     * @param array $data
     * @return bool|array
     */
    public static function update($id, $data)
    {
        if (!$id) {
            return json(['code' => 404, 'message' => '图片不存在']);
        }
        
        if (empty($data['title'])) {
            return json(['code' => 400, 'message' => '标题不能为空']);
        }
        
        return [
            'code' => 200,
            'message' => '更新成功',
            'data' => [
                'id' => $id,
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'alt_text' => $data['alt_text'] ?? '',
                'tags' => $data['tags'] ?? [],
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }
    
    /**
     * 点赞图片
     * @param int $id
     * @param int $userId
     * @return bool|array
     */
    public static function like($id, $userId)
    {
        if (!$id) {
            return json(['code' => 404, 'message' => '图片不存在']);
        }
        
        return [
            'code' => 200,
            'message' => '点赞成功',
            'data' => ['likes_count' => 1],
        ];
    }
    
    /**
     * 获取图片预览
     * @param int $id
     * @return bool|array
     */
    public static function preview($id)
    {
        if (!$id) {
            return json(['code' => 400, 'message' => 'ID 不能为空']);
        }
        
        return [
            'code' => 200,
            'data' => [
                'thumbnail_url' => 'https://example.com/thumb_' . $id . '.jpg',
                'image_url' => 'https://example.com/image_' . $id . '.jpg',
            ],
        ];
    }
    
    /**
     * 删除图片
     * @param int $id
     * @return bool|array
     */
    public static function delete($id)
    {
        if (!$id) {
            return json(['code' => 404, 'message' => '图片不存在']);
        }
        
        return [
            'code' => 200,
            'message' => '删除成功',
            'data' => ['id' => $id],
        ];
    }
}
