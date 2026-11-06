<?php

declare(strict_types=1);

// Yii 的自动加载把命名空间首段当别名解析：restful\controllers\BlogController → @restful/controllers/BlogController.php。
// @app 会在配置阶段被 Application::setBasePath() 覆写，承载不了 restful\ 命名空间，必须单独设这个别名。
// 原来的 @api 没有任何引用（应用 id 叫 api 是另一回事），故移除。
Yii::setAlias('@restful', dirname(__DIR__));
