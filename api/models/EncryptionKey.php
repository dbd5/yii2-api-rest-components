<?php

namespace yrc\api\models;

use yrc\redis\ActiveRecord;
use Yii;

final class EncryptionKey extends ActiveRecord
{
    /**
     * Model attributes
     * @return array
     */
    public function attributes()
    {
        return [
            'id',
            'secret',
            'hash'
        ];
    }

    /**
     * @return \Sodium\crypto_box_publickey
     */
    public function getBoxPublicKey()
    {
        return \Sodium\crypto_box_publickey($this->getBoxKeyPair());
    }

    /**
     * @return \Sodium\crypto_box_keypair
     */
    public function getBoxKeyPair()
    {
        $secret = \base64_decode($this->secret);
        $public = \Sodium\crypto_box_publickey_from_secretkey($secret);
        return \Sodium\crypto_box_keypair_from_secretkey_and_publickey($secret, $public);
    }

    /**
     * Helper method to return an encryption key
     * @return EncryptionKey
     */
    public function generate()
    {
        $boxKp = \Sodium\crypto_box_keypair();
        $obj = new static;
        $obj->secret = \base64_encode(\Sodium\crypto_box_secretkey($boxKp));
        $obj->hash = \hash('sha256', uniqid('__EncryptionKeyPairHash', true));

        if ($obj->save()) {
            return $obj;
        }

        throw new \yii\base\Exception(Yii::t('yrc', 'Failed to generate security tokens'));
    }
}