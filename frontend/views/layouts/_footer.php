<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

?>
<footer id="footer" class="mt-auto py-3 bg-body-tertiary">
    <div class="container">
        <div class="row text-body-secondary">
            <div class="col-md-6 text-center text-md-start">&copy; <?= Html::encode(Yii::$app->name) ?> <?= date('Y') ?></div>
            <div class="col-md-6 text-center text-md-end">
                <a href="https://www.yiiframework.com/" rel="external" class="text-body-secondary text-decoration-none">
                    <?= Yii::t(
                        'yii',
                        'Powered by Yii',                        
                    ) ?>
                    
                </a>
            </div>
        </div>
    </div>
</footer>
