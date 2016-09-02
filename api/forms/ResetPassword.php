<?php

namespace yrc\api\forms;

use Base32\Base32;
use Yii;

abstract class ResetPassword extends \yii\base\model
{
    const SCENARIO_INIT = 'init';
    const SCENARIO_RESET = 'reset';

    const EXPIRY_TIME = '+4 hours';

    /**
     * The email
     * @var string $email
     */
    public $email;

    /**
     * The reset token
     * @var string $reset_token
     */
    public $reset_token;

    /**
     * The new password
     * @var string $password
     */
    public $password;

    /**
     * The new password (again)
     * @var string $password_verify
     */
    public $password_verify;

    /**
     * The user's current password
     * @var string $password_current
     */
    public $password_current;

    /**
     * The user associated to the email
     * @var User $user
     */
    private $user = null;

    /**
     * Validation scenarios
     * @return array
     */
    public function scenarios()
    {
        return [
            self::SCENARIO_INIT => ['email'],
            self::SCENARIO_RESET => ['reset_token'],
        ];
    }

    /**
     * Validation rules
     * @return array
     */
    public function rules()
    {
        return [
            [['email'], 'required', 'on' => self::SCENARIO_INIT],
            [['email'], 'email', 'on' =>  self::SCENARIO_INIT],
            [['email'], 'validateUser', 'on' =>  self::SCENARIO_INIT],

            [['reset_token', 'password', 'password_verify', 'password_current'], 'required', 'on' => self::SCENARIO_RESET],
            [['reset_token'], 'validateResetToken', 'on' => self::SCENARIO_RESET],
            [['password_current'], 'validatePassword', 'on' => self::SCENARIO_RESET],
            [['password', 'password_verify', 'current_password'], 'string', 'min' => 8, 'on' => self::SCENARIO_RESET],
            [['password_verify'], 'compare', 'compareAttribute' => 'password', 'on' => self::SCENARIO_RESET]
        ];
    }

    /**
     * Validates the user's current password
     * @inheritdoc
     */
    public function validatePassword($attributes, $params)
    {
        if (!$this->hasErrors()) {
            if (!$this->user->validatePassword($this->password_current)) {
                $this->addError('password_current', Yii::t('yrc', 'The provided password is not valid'));
            }
        }
    }
    
    /**
     * Validates the users email
     * @inheritdoc
     */
    public function validateUser($attributes, $params)
    {
        if (!$this->hasErrors()) {
            $this->user = Yii::$app->yrc->userClass::findOne(['email' => $this->email]);

            if ($this->user === null) {
                $this->addError('email', Yii::t('yrc', 'The provided email address is not valid'));
            }
        }
    }

    /**
     * Reset token validator
     * @inheritdoc
     */
    public function validateResetToken($attributes, $params)
    {
        if (!$this->hasErrors()) {
            $tokenInfo = Yii::$app->cache->get(
                hash('sha256', $this->reset_token . '_reset_token')
            );
            
            if ($tokenInfo === null) {
                $this->addError('reset_token', Yii::t('yrc', 'The password reset token provided is not valid.'));
            }

            $this->user = Yii::$app->yrc->userClass::find()->where([
                'id' => $tokenInfo['id']
            ])->one();

            if ($this->user === null) {
                $this->addError('reset_token', Yii::t('yrc', 'The password reset token provided is not valid.'));
            }
        }
    }

    /**
     * Sets the user object
     * @param User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
        $this->email = $user->email;
    }

    /**
     * Changes the password for the user
     * @return boolean
     */
    public function reset()
    {
        if ($this->validate()) {
            if ($this->getScenario() === self::SCENARIO_INIT) {
                // Create an reset token for the user, and store it in the cache
                $token = Base32::encode(\random_bytes(64));
                
                Yii::$app->cache->set(hash('sha256', $token . '_reset_token'), [
                    'id' => $this->user->id
                ], strtotime(self::EXPIRY_TIME));

                return Yii::$app->yrc->sendEmail('password_reset', Yii::t('app', 'A request has been made to change your password'), $this->user->email, ['token' => $token]);
            } elseif ($this->getScenario() === self::SCENARIO_RESET) {
                $this->user->password = $this->password;

                if ($this->user->save()) {
                    return Yii::$app->yrc->sendEmail('password_change', Yii::t('app', 'Your password has been changed'), $this->email);
                }
            }
        }

        return false;
    }
}