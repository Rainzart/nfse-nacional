<?php

declare(strict_types = 1);

namespace Hadder\NfseNacional\Common;

use DateInterval;
use DateTime;
use Exception;
use NFePHP\Common\Certificate;
use NFePHP\Common\Exception\RuntimeException;
use NFePHP\Common\Files;
use NFePHP\Common\Strings;
use Throwable;

class RestBase
{
    public $waitingTime = 45;
    protected $certificate;
    protected $disableCertValidation = false;
    protected $tempdir;
    protected $prifile;
    protected $pubfile;
    protected $certfile;
    private Files $filesystem;
    private string $certsdir;

    public function __construct(?Certificate $certificate = null)
    {
        $this->loadCertificate($certificate);
    }

    /**
     * Setao certificado pra comunicação SSL.
     *
     * @return void
     */
    public function loadCertificate(?Certificate $certificate = null)
    {
        $this->isCertificateExpired($certificate);

        if (null !== $certificate) {
            $this->certificate = $certificate;
        }
    }

    /**
     * Apenas para testes, seta a validação de validade do certificado.
     *
     * @param bool $flag
     *
     * @return bool
     */
    public function disableCertValidation($flag = true)
    {
        return $this->disableCertValidation = $flag;
    }

    /**
     * Set another temporayfolder for saving certificates for SOAP utilization.
     *
     * @param string|null $folderRealPath
     *
     * @return void
     */
    public function setTemporaryFolder($folderRealPath = null)
    {
        $mapto = !empty($this->certificate->getCnpj())
            ? $this->certificate->getCnpj()
            : $this->certificate->getCpf();

        if (empty($mapto)) {
            throw new RuntimeException('Foi impossivel identificar o OID do CNPJ ou do CPF.');
        }

        if (empty($folderRealPath)) {
            $path = '/nfse-'
                . $this->uid()
                . '/'
                . $mapto
                . '/';
            $folderRealPath = sys_get_temp_dir() . $path;
        }

        if ('/' !== mb_substr($folderRealPath, -1)) {
            $folderRealPath .= '/';
        }
        $this->tempdir = $folderRealPath;
        $this->setLocalFolder($folderRealPath);
    }

    /**
     * Temporarily saves the certificate keys for use cURL or SoapClient.
     *
     * @return void
     */
    public function saveTemporarilyKeyFiles()
    {
        // certs already exists
        if (!empty($this->certsdir)) {
            return;
        }

        if (!is_object($this->certificate)) {
            throw new RuntimeException('Certificate not found.');
        }

        if (empty($this->filesystem)) {
            $this->setTemporaryFolder();
        }
        // clear dir cert
        $this->removeTemporarilyFiles();
        $this->certsdir = 'certs/';
        $this->prifile  = $this->randomName();
        $this->pubfile  = $this->randomName();
        $this->certfile = $this->randomName();
        $ret            = true;
        // load private key pem

        $private = (string) $this->certificate->privateKey;
        //        if ($this->encriptPrivateKey) {
        //            //replace private key pem with password
        //            $this->temppass = Strings::randomString(16);
        //            //encripta a chave privada entes da gravação do filesystem
        //            openssl_pkey_export(
        //                $this->certificate->privateKey,
        //                $private,
        //                $this->temppass
        //            );
        //        }
        $ret &= $this->filesystem->put(
            $this->prifile,
            $private
        );
        $ret &= $this->filesystem->put(
            $this->pubfile,
            (string) $this->certificate->publicKey
        );
        $ret &= $this->filesystem->put(
            $this->certfile,
            $private . "{$this->certificate}"
        );

        if (!$ret) {
            throw new RuntimeException('Unable to save temporary key files in folder.');
        }
    }

    /**
     * Delete all files in folder.
     *
     * @return void
     */
    public function removeTemporarilyFiles()
    {
        try {
            if (empty($this->filesystem) || empty($this->certsdir)) {
                return;
            }
            // remove os certificados
            $this->filesystem->delete($this->certfile);
            $this->filesystem->delete($this->prifile);
            $this->filesystem->delete($this->pubfile);
            // remove todos os arquivos antigos
            $contents     = $this->filesystem->listContents($this->certsdir, true);
            $dt           = new DateTime();
            $tint         = new DateInterval('PT' . $this->waitingTime . 'M');
            $tint->invert = 1;
            $tsLimit      = $dt->add($tint)->getTimestamp();

            foreach ($contents as $item) {
                if ('file' == $item['type']) {
                    if ($this->filesystem->has($item['path'])) {
                        $timestamp = $this->filesystem->getTimestamp($item['path']);

                        if ($timestamp < $tsLimit) {
                            $this->filesystem->delete($item['path']);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // impedir de ocorrer exception em ambientes muito comporrentes
            // porem nesses casos devem ser feitas limpezas periodicas caso
            // não seja usado o diretorio /tmp pois não será auto limpante
        }
    }

    /**
     * Return uid from user.
     *
     * @return string
     */
    protected function uid()
    {
        return function_exists('posix_getuid') ? posix_getuid() : getmyuid();
    }

    /**
     * Set Local folder for flysystem.
     *
     * @param string $folder
     *
     * @throws Exception
     */
    protected function setLocalFolder($folder = '')
    {
        $this->filesystem = new Files($folder);
    }

    /**
     * Create a unique random file name.
     *
     * @param int $n
     *
     * @return string
     */
    protected function randomName($n = 10)
    {
        $name = $this->certsdir . Strings::randomString($n) . '.pem';

        if (!$this->filesystem->has($name)) {
            return $name;
        }
        $this->randomName($n + 5);
    }

    /**
     * Verifica se o certificado é valido na data atual.
     *
     * @return void
     *
     * @throws Certificate\Exception\Expired
     */
    private function isCertificateExpired(?Certificate $certificate = null)
    {
        if (!$this->disableCertValidation) {
            if (null !== $certificate && $certificate->isExpired()) {
                throw new Certificate\Exception\Expired($certificate);
            }
        }
    }
}
