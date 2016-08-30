<?php

namespace yrc\api\actions;

use app\forms\ResetPassword;
use yrc\rest\Action as RestAction;

use yii\web\HttpException;
use Yii;

/**
 * @class ResetPasswordAction
 * Handles token refresh
 */
class ResetPasswordAction extends RestAction
{
    /**
     * [POST] /api/[...]/reset_password
     * Allows a user to reset their password
     * @return mixed
     */
    public static function post($params)
    {
        static $form;
        $token = Yii::$app->request->post('reset_token', false);

        // Determine the correct scenario to use based upon the reset token
        if ($token === false) {
            $form = new ResetPassword(['scenario' => ResetPassword::SCENARIO_INIT]);
        } else {
            $form = new ResetPassword(['scenario' => ResetPassword::SCENARIO_RESET]);
        }
        
        // Load the form
        if ($form->load(['ResetPassword' => Yii::$app->request->post()])) {
            $form->password = Yii::$app->request->post('password', null);
            $form->password_verify = Yii::$app->request->post('password_verify', null);

            // If the user is authenticated, populate the model
            if (!Yii::$app->user->isGuest) {
                $user = Yii::$app->yrc->userClass::findOne(['id' => Yii::$app->user->id]);
                $form->setUser($user);
            } else {
                $form->email = Yii::$app->request->post('email', null);
            }

            // Validate the form and make sure all of the attributes are set, then perform the reset task depending upon the scenario
            if ($form->validate()) {
                return $form->reset();
            }

            throw new HttpException(400, \json_encode($form->getErrors()));
        }
            
        return false;
    }
}