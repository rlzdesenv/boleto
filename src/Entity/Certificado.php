<?php
/**
 * Created by PhpStorm.
 * User: elvis
 * Date: 17/03/2018
 * Time: 14:16
 */

namespace Boleto\Entity;


class Certificado
{

    private \OpenSSLCertificate|false $signcert;
    private false|\OpenSSLAsymmetricKey $privkey;
    private string $pemContent;

    /**
     * Certificado constructor.
     * @throws \Exception
     */
    public function __construct($file, $password)
    {
        $pfx = file_get_contents($file);
        if (!openssl_pkcs12_read($pfx, $result, $password)) {
            throw new \Exception('Não foi possível ler o certificado .pfx');
        }

        $this->signcert = openssl_x509_read($result['cert']);
        $this->privkey = openssl_pkey_get_private($result['pkey'], $password);

        // Combinar a chave privada e o certificado no formato PEM
        $this->pemContent = $result['pkey'] . "\n" . $result['cert'];

        // Incluir certificados intermediários (se houver)
        if (!empty($result['extracerts'])) {
            foreach ($result['extracerts'] as $extraCert) {
                $this->pemContent .= "\n" . $extraCert;
            }
        }

        return $this;
    }

    /**
     * @return bool|resource
     */
    public function getSignCert()
    {
        return $this->signcert;
    }

    /**
     * @return bool|resource
     */
    public function getPrivKey()
    {
        return $this->privkey;
    }

    /**
     * @throws \Exception
     */
    public function signText($txt)
    {
        try {

            //https://github.com/BoletoNet/boletonet/issues/306

            $file = tempnam(sys_get_temp_dir(), 'php');
            $file_sign = tempnam(sys_get_temp_dir(), 'php');
            file_put_contents($file, $txt);

            openssl_pkcs7_sign($file, $file_sign, $this->signcert, $this->privkey, [], PKCS7_BINARY | PKCS7_TEXT);

            $signature = file_get_contents($file_sign);
            $parts = preg_split("#\n\s*\n#Uis", $signature);
            $base64 = $parts[1];

            unlink($file);
            unlink($file_sign);

            return $base64;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    public function getCertificateFilePem(): string
    {
        $name = uniqid() . '.pem';

        $file = trim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . ltrim($name, DIRECTORY_SEPARATOR);

        file_put_contents($file, $this->pemContent);

        register_shutdown_function(function () use ($file) {
            @unlink($file);
        });

        return $file;
    }

}
