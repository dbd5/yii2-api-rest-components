<?php

namespace yrc\actions;

use common\forms\ChangeEmail;
use yrc\rest\Action as RestAction;

use yii\web\HttpException;
use Yii;

/**
 * Handles enabling and disabling of OTP
 * @class ChangeEmailAction
 */
class ChangeEmailAction extends RestAction
{
    /**
     * Allows the user to change the email address associated to their account
     * @return boolean
     */
    public function post($params)
    {
        $model = new ChangeEmail;
        
        if ($model->load(['ChangeEmail' => Yii::$app->request->post()])) {
            $model->setUser(Yii::$app->user->identityClass::findOne([
                'id' => Yii::$app->user->id
            ]));

            if ($model->change()) {
                return true;
            }

            throw new HttpException(400, \json_encode($model->getErrors()));
        }
            
        return false;
    }
}
