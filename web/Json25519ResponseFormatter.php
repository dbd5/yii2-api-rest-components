<?php

namespace yrc\web;

use yrc\web\JsonResponseFormatter;
use yii\web\NotAcceptableHttpException;
use Yii;

class Json25519ResponseFormatter extends JsonResponseFormatter
{
    /**
     * Take the response generated by JsonResponseFormatter and anonymously encrypt it
     * @param array $response
     */
    protected function formatJson($response)
    {
        parent::formatJson($response);
        $response->getHeaders()->set('Content-Type', 'application/json+25519; charset=UTF-8');

        // If we do not have a user identity in place we cannot encrypt the response. Tell the user the Accept headers are not acceptable
        if (Yii::$app->user->isGuest) {
            throw new NotAcceptableHttpException;
        }

        // Retrieve the token object from the user
        $token = Yii::$app->user->getIdentity()->getToken();

        // Abort if we don't get a token back.
        if ($token === null) {
            throw new NotAcceptableHttpException;
        }

        // Calculate the keypair
        $keyPair = \Sodium\crypto_box_keypair_from_secretkey_and_publickey(
            \base64_decode($token->getCryptToken()->secret_box_kp),
            \base64_decode($token->getCryptToken()->client_public)
        );

        // Encrypt the content
        $nonce = \Sodium\randombytes_buf(\Sodium\CRYPTO_BOX_NONCEBYTES);
        $content = \Sodium\crypto_box(
            $response->content,
            $nonce,
            $keyPair
        );

        $signature = \Sodium\crypto_sign_detached(
            $content,
            \base64_decode($token->getCryptToken()->secret_sign_kp)
        );

        // Calculate a nonce and set it in the header
        $response->getHeaders()->set('x-nonce', \base64_encode($nonce));

        // Send the public key in the clear. The client may need this on the initial authentication request
        $response->getHeaders()->set('x-pubkey', \base64_encode($token->getCryptToken()->getBoxPublicKey()));
        $response->getHeaders()->set('x-sigpubkey', \base64_encode($token->getCryptToken()->getSignPublicKey()));
        // Sign the raw response and send the signature alongside the header
        $response->getHeaders()->set('x-signature', \base64_encode($signature));

        // Update the response content
        $response->content = \base64_encode($content);
    }
}