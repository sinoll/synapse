<?php
declare(strict_types=1);
use common\models\Post;
use common\models\SharedFile;
use yii\bootstrap5\BootstrapPluginAsset;
use yii\bootstrap5\Html;
/** @var SharedFile[] $files */
$fileIcon = static function (SharedFile $file): string {
    $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
    return match (true) {
        in_array($extension, ['pdf'], true) => 'bi-file-earmark-pdf-fill text-danger',
        in_array($extension, ['doc', 'docx', 'odt'], true) => 'bi-file-earmark-word-fill text-primary',
        in_array($extension, ['xls', 'xlsx', 'ods', 'csv'], true) => 'bi-file-earmark-spreadsheet-fill text-success',
        in_array($extension, ['ppt', 'pptx', 'odp'], true) => 'bi-file-earmark-slides-fill text-warning',
        in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) => 'bi-file-earmark-image-fill text-info',
        in_array($extension, ['mp3', 'wav'], true) => 'bi-file-earmark-music-fill text-purple',
        in_array($extension, ['mp4', 'webm'], true) => 'bi-file-earmark-play-fill text-danger',
        in_array($extension, ['zip', '7z', 'rar'], true) => 'bi-file-earmark-zip-fill text-warning',
        in_array($extension, ['json', 'xml', 'html', 'css', 'js', 'php'], true) => 'bi-file-earmark-code-fill text-secondary',
        in_array($extension, ['txt', 'md'], true) => 'bi-file-earmark-text-fill text-secondary',
        default => 'bi-file-earmark-fill text-secondary',
    };
};
$previewableExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt', 'md', 'csv', 'mp3', 'wav', 'mp4', 'webm'];
$this->title = '文件';
$this->registerCssFile('@web/css/content-file.css');
BootstrapPluginAsset::register($this);
?>
<div class="nextcloud-drive">
    <aside class="drive-sidebar">
        <?= Html::a('＋ 上传文件', ['/file/create'], ['class' => 'drive-upload-button']) ?>
        <?= Html::a('所有文件', ['/file/index'], ['class' => !$trash ? 'active' : '']) ?>
        <?= Html::a('已删除', ['/file/index', 'trash' => true], ['class' => $trash ? 'active' : '']) ?>
        <?= Html::beginForm(['/file/create-folder'], 'post', ['class' => 'drive-folder-create']) ?>
            <?= Html::hiddenInput('parent_id', $folderId) ?>
            <?= Html::textInput('name', '', ['placeholder' => '新建文件夹', 'aria-label' => '新建文件夹名称']) ?>
            <?= Html::submitButton('＋', ['title' => '创建文件夹']) ?>
        <?= Html::endForm() ?>
    </aside>
    <section class="drive-main">
        <header class="drive-heading"><div><h1><?= $trash ? '已删除' : Html::encode($folderModel?->name ?? '所有文件') ?></h1><p>文件安全存储，并按需与他人分享。</p></div><?= Html::a('＋ 上传文件', ['/file/create', 'folder' => $folderId], ['class' => 'drive-upload-button']) ?></header>
        <?php if ($folderModel !== null): ?><nav class="drive-breadcrumb"><?= Html::a('所有文件', ['/file/index']) ?> <span>›</span> <?= Html::encode($folderModel->name) ?></nav><?php endif; ?>
        <?php if (!$trash && $folders !== []): ?><div class="drive-folder-grid"><?php foreach ($folders as $folder): ?><?= Html::a('<i class="bi bi-folder-fill" aria-hidden="true"></i><span>' . Html::encode($folder->name) . '</span>', ['/file/index', 'folder' => $folder->id], ['class' => 'drive-folder-card']) ?><?php endforeach; ?></div><?php endif; ?>
        <div class="drive-table-wrap"><table class="drive-table"><thead><tr><th>名称</th><th>大小</th><th>修改时间</th><th>分享</th><th>操作</th></tr></thead><tbody>
        <?php foreach ($files as $file): ?><tr>
            <td><i class="bi <?= Html::encode($fileIcon($file)) ?> drive-file-icon" aria-hidden="true"></i><span class="drive-file-name"><?= Html::encode($file->original_name) ?><small><?= Html::encode($file->mime_type ?? '文件') ?></small></span></td>
            <td><?= Yii::$app->formatter->asShortSize((int) $file->size_bytes) ?></td>
            <td><?= Yii::$app->formatter->asRelativeTime($file->updated_at) ?></td>
            <td><?php $visibility = (int) $file->post->visibility; ?><span class="drive-badge"><?= ['仅自己', '登录用户', '公开', '指定用户'][$visibility] ?? '仅自己' ?></span></td>
            <td class="drive-actions">
                <details class="drive-context-menu">
                    <summary class="drive-action-button" title="更多操作" aria-label="更多操作"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></summary>
                    <div class="drive-context-popover">
                        <?php if ($trash): ?>
                            <?= Html::beginForm(['/file/restore', 'id' => $file->id], 'post') ?>
                            <?= Html::submitButton('<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> 恢复', ['class' => 'drive-menu-item']) ?>
                            <?= Html::endForm() ?>
                        <?php else: ?>
                            <?php if (in_array(strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION)), $previewableExtensions, true)): ?>
                                <button type="button" class="drive-menu-item" data-bs-toggle="modal" data-bs-target="#filePreviewModal" data-preview-url="<?= Html::encode(\yii\helpers\Url::to(['/file/preview', 'id' => $file->id])) ?>" data-preview-title="<?= Html::encode($file->original_name) ?>"><i class="bi bi-eye-fill" aria-hidden="true"></i> 预览</button>
                            <?php endif; ?>
                            <details class="drive-menu-share">
                                <summary class="drive-menu-item"><i class="bi bi-share-fill" aria-hidden="true"></i> 分享</summary>
                                <div class="drive-share-popover">
                                    <?= Html::beginForm(['/file/share', 'id' => $file->id], 'post') ?>
                                    <label>谁可以访问<?= Html::dropDownList('visibility', $visibility, [Post::VISIBILITY_PRIVATE => '仅自己', Post::VISIBILITY_REGISTERED_USERS => '登录用户', Post::VISIBILITY_PUBLIC => '任何人（公开）', Post::VISIBILITY_SELECTED_USERS => '指定用户'], ['class' => 'form-select', 'data-share-visibility' => '1']) ?></label>
                                    <label>指定用户名<?= Html::textInput('recipients', '', ['class' => 'form-control', 'placeholder' => '用逗号分隔', 'data-recipients' => '1']) ?></label>
                                    <?= Html::submitButton('<i class="bi bi-check2" aria-hidden="true"></i> 保存分享设置', ['class' => 'drive-upload-button']) ?>
                                    <?= Html::endForm() ?>
                                    <?= Html::a('<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> 打开分享页面', ['/content/view', 'id' => $file->post_id], ['target' => '_blank', 'rel' => 'noopener']) ?>
                                </div>
                            </details>
                            <?= Html::a('<i class="bi bi-pencil-square" aria-hidden="true"></i> 编辑', ['/file/update', 'id' => $file->id], ['class' => 'drive-menu-item']) ?>
                            <?= Html::a('<i class="bi bi-download" aria-hidden="true"></i> 下载', ['/file/download', 'id' => $file->id], ['class' => 'drive-menu-item']) ?>
                            <?= Html::beginForm(['/file/delete', 'id' => $file->id], 'post', ['onsubmit' => "return confirm('将文件移到已删除吗？')"]) ?>
                            <?= Html::submitButton('<i class="bi bi-trash3-fill" aria-hidden="true"></i> 移到已删除', ['class' => 'drive-menu-item is-danger']) ?>
                            <?= Html::endForm() ?>
                        <?php endif; ?>
                    </div>
                </details>
            </td>
        </tr><?php endforeach; ?>
        <?php if ($files === []): ?><tr><td colspan="5" class="drive-empty"><strong><?= $trash ? '回收站是空的' : '这里还没有文件' ?></strong><span><?= $trash ? '删除的文件会显示在这里。' : '上传文件，或在左侧新建文件夹。' ?></span></td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</div>
<?php if (!$trash): ?>
<div class="modal fade" id="filePreviewModal" tabindex="-1" aria-labelledby="filePreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content drive-preview-modal">
            <div class="modal-header"><h2 class="modal-title fs-5" id="filePreviewTitle">文件预览</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button></div>
            <div class="modal-body"><iframe class="drive-preview-frame" title="文件预览" src="about:blank"></iframe></div>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
document.querySelectorAll('[data-share-visibility]').forEach((select) => {
    const recipients = select.closest('form').querySelector('[data-recipients]');
    const update = () => { recipients.disabled = select.value !== '3'; recipients.closest('label').hidden = select.value !== '3'; };
    select.addEventListener('change', update); update();
});
const previewModal = document.getElementById('filePreviewModal');
if (previewModal) {
    const previewFrame = previewModal.querySelector('.drive-preview-frame');
    previewModal.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        previewModal.querySelector('#filePreviewTitle').textContent = trigger.dataset.previewTitle || '文件预览';
        previewFrame.src = trigger.dataset.previewUrl;
    });
    previewModal.addEventListener('hidden.bs.modal', () => { previewFrame.src = 'about:blank'; });
}
</script>
