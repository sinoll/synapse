<?php

namespace app\restful\models;

use yii\base\Model;

/**
 * Moment (动态) 模型
 */
class Moment extends Model
{
    public $id;
    public $content;
    public $user_id;
    public $likes = [];
    public $comments = [];
    public $image_url;
    public $location;
    public $created_at;
    public $updated_at;
    
    /**
     * 查找所有动态
     * @param int $userId 可选：筛选特定用户的动态
     * @return array
     */
    public static function findAll($userId = null)
    {
        $moments = [
            [
                'id' => 1,
                'content' => '今天天气真好！',
                'user_id' => 1,
                'likes' => [2, 3, 4],
                'comments' => ['太棒了', '是啊'],
                'image_url' => 'https://example.com/image1.jpg',
                'location' => '北京',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
        
        return $userId ? array_filter($moments, fn($m) => $m['user_id'] == $userId) : $moments;
    }
    
    /**
     * 查找单个动态
     * @param int $id
     * @return array
     */
    public static function findOne($id)
    {
        if (!$id) return [];
        
        return [
            'id' => $id,
            'content' => '这是一个动态内容',
            'user_id' => 1,
            'likes' => [],
            'comments' => [],
            'image_url' => '',
            'location' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * 创建动态
     * @param array $data
     * @return bool|array
     */
    public static function create($data)
    {
        if (empty($data['content'])) {
            return json(['code' => 400, 'message' => '内容不能为空']);
        }
        
        $id = mt_rand(100, 999);
        
        return [
            'code' => 200,
            'message' => '创建成功',
            'data' => [
                'id' => $id,
                'content' => $data['content'],
                'user_id' => $data['user_id'] ?? 1,
                'likes' => [],
                'comments' => [],
                'image_url' => $data['image_url'] ?? '',
                'location' => $data['location'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }
    
    /**
     * 更新动态
     * @param int $id
     * @param array $data
     * @return bool|array
     */
    public static function update($id, $data)
    {
        if (!$id) {
            return json(['code' => 404, 'message' => '动态不存在']);
        }
        
        if (empty($data['content'])) {
            return json(['code' => 400, 'message' => '内容不能为空']);
        }
        
        return [
            'code' => 200,
            'message' => '更新成功',
            'data' => [
                'id' => $id,
                'content' => $data['content'],
                'user_id' => $data['user_id'] ?? 1,
                'image_url' => $data['image_url'] ?? '',
                'location' => $data['location'] ?? '',
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }
    
    /**
     * 点赞动态
     * @param int $id
     * @param int $userId
     * @return bool|array
     */
    public static function like($id, $userId)
    {
        if (!$id) {
            return json(['code' => 404, 'message' => '动态不存在']);
        }
        
        return [
            'code' => 200,
            'message' => '点赞成功',
            'data' => ['likes_count' => 1],
        ];
    }
    
    /**
     * 删除动态
     * @param int $id
     * @return bool|array
     */
    public static function delete($id)
    {
        if (!$id) {
            return json(['code' => 404, 'message' => '动态不存在']);
        }
        
        return [
            'code' => 200,
            'message' => '删除成功',
            'data' => ['id' => $id],
        ];
    }
}
