<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

use mihaildev\ckeditor\CKEditor;
?>

<div class="row">
    <?php $form = ActiveForm::begin([
        'layout' => 'horizontal',
        'fieldConfig' => [
            'template' => "{label}\n<div class=\"col-lg-3\">{input}</div>\n<div class=\"col-lg-8\">{error}</div>",
            'labelOptions' => ['class' => 'col-lg-1 control-label'],
        ],
    ]);?>

    <?= $form->field($model, 'amount')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'currency')->dropDownList($model->currenciesArray, ['class'=>'']) ?>

    <?= $form->field($model, 'description')->textArea(['maxlength' => true]) ?>

    <div class="form-group">
        <?= Html::submitButton('Pay', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
