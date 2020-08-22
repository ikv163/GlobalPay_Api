<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;

/* @var $this yii\web\View */
/* @var $model app\models\SysBankcard */

$this->params['breadcrumbs'] = ['label' => Yii::t('app/menu', 'Register')];
$this->title = \Yii::t('app/menu', 'Invite_Register');

?>

<style>
    .invite_reg{
        width:90%; margin:0 auto;
    }
</style>

<div class="invite_reg">

    <?php $form = ActiveForm::begin(); ?>

    <div class="row">
        <?= $form->field($model, 'invite_code')->textInput(['maxlength' => true, 'value'=>$invite_code,'readonly'=>'readonly']) ?>
    </div>

    <div class="row">
        <?= $form->field($model, 'username')->textInput(['maxlength' => true]) ?>
    </div>

    <div class="row">
        <?= $form->field($model, 'login_password')->passwordInput(['maxlength' => true]) ?>
    </div>

    <div class="row">
        <?= $form->field($model, 'confirm_password')->passwordInput(['maxlength' => true]) ?>
    </div>

    <div class="row">
        <?= $form->field($model, 'verify_code')->widget(\yii\captcha\Captcha::className(), [
            'template' => '<div class="row"><div class="col-lg-7">{input}</div><div class="col-lg-4">{image}</div></div>',
            'imageOptions' => ['title' => '换一个', 'alt' => '验证码', 'style' => 'cursor:pointer;']
        ]); ?>
    </div>

    <?php if($error){ ?>
    <div class="row">
        <?= $form->field($model, 'error')->hiddenInput(['maxlength' => true]) ?>
    </div>
    <?php } ?>


    <div class="row">
        <div class="form-group">
            <?= Html::submitButton(Yii::t('app/menu', 'Save'), ['class' => 'btn btn-success']) ?>
        </div>
    </div>


    <?php ActiveForm::end(); ?>

</div>