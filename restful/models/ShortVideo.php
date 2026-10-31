<?php

namespace app\restful\models;

use yii\base\Model;

/**
 * Short Video (短视频) 模型
 */
class ShortVideo extends Model
{
    public $id;
    public $title;
    public $description;
    public $video_url;
    public $cover_url;
    public $user_id;
    public $duration;
    public $width;
    public $height;
    public $likes = [];
    public $plays;
    public $created_at;
    public $updated_at;
    
    /**
     * 查找所有短视频
     * @param int $userId 可选：筛选特定用户的视频
     * @return array
     */
    public static function findAll($userId = null)
    {
        $videos = [
            [
                'id' => 1,
                'title' => '我的第一个短视频',
                'description' => '这是一个测试视频',
                'video_url' => 'https://example.com/video1.mp4',
                'cover_url' => 'https://example.com/cover1.jpg',
                'user_id' => 1,
                'duration' => 60,
                'width' => 1080,
                'height' => 1920,
                'likes' => [2, 3, 4],
                'plays' => 1000,
                'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
        
        return $userId ? array_filter($videos, fn($v) => $v['user_id'] == $userId) : $videos;
    }
    
    /**
     * 查找单个短视频
     * @param int $id
     * @return array
     */
    public static function findOne($id)
    {
        if (!$id) return [];
        
        return [
            'id' => $id,
            'title' => '视频标题',
            'description' => '这是视频的描述...',
            'video_url' => 'https://example.com/video.mp4',
            'cover_url' => 'https://example.com/cover.jpg',
            'user_id' => 1,
            'duration' => 30,
            'width' => 1080,
            'height' => 1920,
            'likes' => [],
            'plays' => 500,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * 创建短视频
     * @param array $data
     * @return bool|array
     */
    public static function create($data)
    {
        if (empty($data['title'])) {
            return json(['code' => 400, 'message' => '标题不能为空']);
        }
        
        if (empty($data['video_url'])) {
            return json(['code' => 400, 'message' => '视频 URL 不能为空']);
        }
        
        $id = mt_rand(100, 999);
        
        return [
            'code' => 200,
            'message' => '创建成功',
            'data' => [
                'id' => $id,
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'video_url' => $data['video_url'],
                'cover_url' => $data['cover_url'] ?? '',
                'user_id' => $data['user_id'] ?? 1,
                'duration' => $data['duration'] ?? 0,
                'width' => $data['width'] ?? 0,
                'height' => $data['height'] ?? 0,
                'likes' => [],
                'plays' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }
    
    /**
     * 更新短视频
     * @param int $id
     * @param array $data
     * @return bool|array
     */
    public static function update($id, $data)
    {
        if (!$id) {
            return json(['code' => 404, 'message' => '视频不存在']);
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
                'cover_url' => $data['cover_url'] ?? '',
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }
    
    /**
     * 点赞视频
     * @param int $id
     * @param int $userId
     * @return bool|array
     */
    public static function like($id, $userId)
    {
        if (!$id) {
            return json(['code' => 404, 'message' => '视频不存在']);
        }
        
        return [
            'code' => 200,
            'message' => '点赞成功',
            'data' => ['likes_count' => 1],
        ];
    }
    
    /**
     * 删除视频
     * @param int $id
     * @return bool|array
     */
    public static function delete($id)
    {
        if (!$id) {
            return json(['code' => 404, 'message' => '视频不存在']);
        }
        
        return [
            'code' => 200,
            'message' => '删除成功',
            'data' => ['id' => $id],
        ];
    }
}
