<?php
/**
 * @pluginName epoint Opencart 3.x Payment Gateway
 * @pluginUrl https://epoint.az/
 * @varion 1.0.0
 * @author Rauf ABBASZADE <rafo.abbas@gmail.com>
 * @authorURI: https://abbasazade.dev/
 */

class Epoint
{
    /**
     * Epoint public key
     * @var string
     */
    public $publicKey;

    /**
     * Epoint private key
     * @var string
     */
    public $privateKey;

    /**
     * Epoint request url
     * @var string
     */
    public $baseUrl = 'https://epoint.az/api';

    /**
     * Epoint signature
     * @var string
     */
    public $signature = '';


    public function request($url, $data = [])
    {
        $POSTFIELDS = http_build_query($data);
        $_ch = curl_init();
        curl_setopt($_ch, CURLOPT_URL, $this->baseUrl .'/'. $url);
        curl_setopt($_ch, CURLOPT_POSTFIELDS, $POSTFIELDS);
        curl_setopt($_ch, CURLOPT_RETURNTRANSFER, TRUE);
        return curl_exec($_ch);
    }

    public function payload($payload)
    {
        $data = base64_encode(json_encode($payload));

        $this->generateSignature($payload);

        return [
            'data'      => $data,
            'signature' => $this->getSignature(),
        ];
    }

    public function generateSignature($payload)
    {
        $data = base64_encode(json_encode($payload));

        $this->setSignature(base64_encode(sha1($this->getPrivateKey() . $data . $this->getPrivateKey(), 1)));
    }

    public function getSignature()
    {
        return $this->signature;
    }

    public function setSignature($signature)
    {
        $this->signature = $signature;
        return $this;
    }

    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    public function setPrivateKey($privateKey)
    {
        $this->privateKey = $privateKey;
        return $this;
    }

    public function getPublicKey()
    {
        return $this->publicKey;
    }

    public function setPublicKey($publicKey)
    {
        $this->publicKey = $publicKey;
        return $this;
    }
}