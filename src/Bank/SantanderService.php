<?php
/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 19/11/2024
 * Time: 09:05
 */

namespace Boleto\Bank;


use Boleto\Entity\Beneficiario;
use Boleto\Entity\Certificado;
use Boleto\Entity\Desconto;
use Boleto\Entity\Juros;
use Boleto\Entity\Multa;
use Boleto\Entity\Pagador;
use Boleto\Helper\Helper;
use Cache\Adapter\Apcu\ApcuCachePool;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use stdClass;

class SantanderService extends AbstractBank implements InterfaceBank
{


    private ?DateTime $vencimento;
    private ?DateTime $emissao;
    private ?float $valor;
    private ?int $agencia;
    private ?int $conta;

    private ?string $convenio;
    private ?string $nossonumero;
    private string $codigobarras;
    private string $linhadigitavel;
    private ?string $pixqrcode;
    private int $prazodevolucao = 0;
    private bool $pix = false;
    private ?Pagador $pagador;
    private ?Beneficiario $beneficiario;
    private ?Certificado $certificado;
    private Juros $juros;
    private Multa $multa;
    /**
     * @var Desconto[]
     */
    private array $desconto = [];

    private ApcuCachePool $cache;
    private bool $sandbox = false;

    private ?string $clientId = null;
    private ?string $secretId = null;
    private ?string $workspaceId = null;

    private ?string $chavePix = null;

    /**
     * SantanderService constructor.
     * @param DateTime|null $vencimento
     * @param null $valor
     * @param null $nossonumero
     * @param null $agencia
     * @param null $conta
     * @param Pagador|null $pagador
     * @param Beneficiario|null $beneficiario
     * @param Certificado|null $certificado
     */
    public function __construct(DateTime $vencimento = null, $valor = null, $nossonumero = null, $agencia = null, $conta = null, $convenio = null, Pagador $pagador = null, Beneficiario $beneficiario = null, Certificado $certificado = null, $clientId = null, $secretId = null)
    {
        $this->cache = \Boleto\Factory\CacheFactory::getCache();

        $this->vencimento = $vencimento;
        $this->valor = $valor;
        $this->nossonumero = $nossonumero;
        $this->agencia = $agencia;
        $this->conta = $conta;
        $this->convenio = $convenio;
        $this->pagador = $pagador;
        $this->beneficiario = $beneficiario;
        $this->certificado = $certificado;
        $this->clientId = $clientId;
        $this->secretId = $secretId;

    }

    /**
     * @param DateTime $date
     * @return SantanderService
     */
    public function setEmissao(DateTime $date): SantanderService
    {
        $this->emissao = $date;
        return $this;
    }

    /**
     * @param DateTime $date
     * @return SantanderService
     */
    public function setVencimento(DateTime $date): SantanderService
    {
        $this->vencimento = $date;
        return $this;
    }

    /**
     * @param double $valor
     * @return SantanderService
     */
    public function setValor(float $valor): SantanderService
    {
        $this->valor = $valor;
        return $this;
    }

    /**
     * @param int $nossonumero
     * @return SantanderService
     */
    public function setNossoNumero(int $nossonumero): SantanderService
    {
        $this->nossonumero = $nossonumero;
        return $this;
    }

    /**
     * @param int $agencia
     * @return SantanderService
     */
    public function setAgencia(int $agencia): SantanderService
    {
        $this->agencia = $agencia;
        return $this;
    }

    /**
     * @param int $conta
     * @return SantanderService
     */
    public function setConta(int $conta): SantanderService
    {
        $this->conta = $conta;
        return $this;
    }


    /**
     * @param Pagador|null $pagador
     * @return SantanderService
     */
    public function setPagador(Pagador $pagador = null): SantanderService
    {
        $this->pagador = $pagador;
        return $this;
    }

    /**
     * @param Beneficiario|null $beneficiario
     * @return SantanderService
     */
    public function setBeneficiario(Beneficiario $beneficiario = null): SantanderService
    {
        $this->beneficiario = $beneficiario;
        return $this;
    }

    /**
     * @return Beneficiario
     */
    public function getBeneficiario(): Beneficiario
    {
        return $this->beneficiario;
    }

    /**
     * @param string $clientId
     * @return SantanderService
     */
    public function setClientId(string $clientId): SantanderService
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * @return string
     */
    private function getClientId(): string
    {
        if (is_null($this->clientId)) {
            throw new InvalidArgumentException('O parâmetro clientId nulo.');
        }
        return $this->clientId;
    }


    public function setSecretId(string $secretId): SantanderService
    {
        $this->secretId = $secretId;
        return $this;
    }


    public function getSecretId(): ?string
    {
        if (is_null($this->secretId)) {
            throw new InvalidArgumentException('O parâmetro secretId nulo.');
        }
        return $this->secretId;
    }

    public function getWorkspaceId(): ?string
    {
        if (is_null($this->workspaceId)) {
            throw new InvalidArgumentException('O parâmetro workspaceId nulo.');
        }
        return $this->workspaceId;
    }

    public function setWorkspaceId(?string $workspaceId): SantanderService
    {
        $this->workspaceId = $workspaceId;
        return $this;
    }

    public function getChavePix(): ?string
    {
        if (is_null($this->chavePix)) {
            throw new InvalidArgumentException('O parâmetro chave pix nulo.');
        }
        return $this->chavePix;
    }

    public function setChavePix(?string $chavePix): SantanderService
    {
        $this->chavePix = $chavePix;
        return $this;
    }


    /**
     * @param $certificado Certificado
     * @return SantanderService
     */
    public function setCertificado(Certificado $certificado): SantanderService
    {
        $this->certificado = $certificado;
        return $this;
    }


    /**
     * @param string $codigobarras
     */
    private function setCodigobarras(string $codigobarras): void
    {
        $this->codigobarras = $codigobarras;
    }

    /**
     * @param string $linhadigitavel
     */
    private function setLinhadigitavel(string $linhadigitavel): void
    {
        $this->linhadigitavel = $linhadigitavel;
    }

    /**
     * @return DateTime
     */
    public function getEmissao(): DateTime
    {
        if (empty($this->emissao)) {
            throw new InvalidArgumentException('Data Emissäo inválido.');
        }
        return $this->emissao;
    }

    public function getVencimento(): ?DateTime
    {
        if (empty($this->vencimento)) {
            throw new InvalidArgumentException('Data Vencimento inválido.');
        }
        return $this->vencimento;
    }


    /**
     * @return double
     */
    public function getValor(): ?float
    {
        if (is_null($this->valor)) {
            throw new InvalidArgumentException('Valor inválido.');
        }
        return $this->valor;
    }

    /**
     * @return string
     */
    public function getNossoNumero(): ?string
    {
        if (is_null($this->nossonumero)) {
            throw new InvalidArgumentException('Nosso Numero inválido.');
        }
        return $this->nossonumero;
    }

    /**
     * @return string
     */
    public function getLinhaDigitavel(): string
    {
        return $this->linhadigitavel;
    }

    /**
     * @return string
     */
    public function getCodigoBarras(): string
    {
        return $this->codigobarras;
    }

    /**
     * @return int|string|null
     */
    private function getAgencia(): int|string|null
    {
        if (is_null($this->agencia)) {
            throw new InvalidArgumentException('Agência inválido.');
        }
        return $this->agencia;
    }

    /**
     * @return int|string|null
     */
    private function getConta(): int|string|null
    {
        if (is_null($this->conta)) {
            throw new InvalidArgumentException('Conta inválido.');
        }
        return $this->conta;
    }

    public function getConvenio(): ?string
    {
        if (is_null($this->convenio)) {
            throw new \InvalidArgumentException('Convênio inválido.');
        }
        return $this->convenio;
    }

    public function setConvenio(?string $convenio): SantanderService
    {
        $this->convenio = $convenio;
        return $this;
    }


    /**
     * @return Juros
     */
    public function getJuros(): Juros
    {
        return $this->juros;
    }

    /**
     * @param Juros $juros
     * @return SantanderService
     */
    public function setJuros(Juros $juros): SantanderService
    {
        $this->juros = $juros;
        return $this;
    }

    /**
     * @return Multa
     */
    public function getMulta(): Multa
    {
        return $this->multa;
    }

    /**
     * @param Multa $multa
     * @return SantanderService
     */
    public function setMulta(Multa $multa): SantanderService
    {
        $this->multa = $multa;
        return $this;
    }

    /**
     * @return Desconto[]
     */
    public function getDesconto(): array
    {
        return $this->desconto;
    }

    /**
     * @param Desconto $desconto
     * @return SantanderService
     */
    public function setDesconto(Desconto $desconto): SantanderService
    {
        $this->desconto[] = $desconto;
        return $this;
    }

    /**
     * @return Certificado
     */
    private function getCertificado(): Certificado
    {

        return $this->certificado;

    }

    private function getCertificateFilePem(): string
    {
        return $this->tmpfile(uniqid() . '.pem', $this->certificado . PHP_EOL . $this->certificado->getPrivKey());
    }

    /**
     * @return string
     */
    private function getNumeroNegociacao(): string
    {
        return str_pad($this->getAgencia(), 4, "0", STR_PAD_LEFT) . str_pad($this->getConta(), 14, "0", STR_PAD_LEFT);
    }


    /**
     * @return string|null
     */
    public function getPixQrCode(): ?string
    {
        return $this->pixqrcode;
    }

    /**
     * @param string|null $pixqrcode
     * @return SantanderService
     */
    public function setPixQrCode(?string $pixqrcode): SantanderService
    {
        $this->pixqrcode = $pixqrcode;
        return $this;
    }


    /**
     * @return bool
     */
    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * @param bool $sandbox
     * @return SantanderService
     */
    public function setSandbox(bool $sandbox): SantanderService
    {
        $this->sandbox = $sandbox;
        return $this;
    }


    /**
     * @return int
     */
    public function getPrazoDevolucao(): int
    {
        return $this->prazodevolucao ?: 0;
    }

    /**
     * @param mixed $prazodevolucao
     * @return SantanderService
     */
    public function setPrazoDevolucao(int $prazodevolucao): SantanderService
    {
        $this->prazodevolucao = $prazodevolucao;
        return $this;
    }


    /**
     * @return boolean
     */
    public function getGerarPix(): bool
    {
        return $this->pix;
    }

    /**
     * @param bool $pix
     * @return SantanderService
     */
    public function setGerarPix(bool $pix): SantanderService
    {
        $this->pix = $pix;
        return $this;
    }


    /**
     * @throws \Boleto\Exception\InvalidArgumentException
     * @throws GuzzleException
     * @throws Exception
     */
    public function send(): void
    {

        try {

            $now = new DateTime();


            $arr = new stdClass();

            $arr->environment = "PRODUCAO";
            $arr->nsuCode = $this->getNossoNumero();
            $arr->nsuDate = $now->format('Y-m-d');
            $arr->covenantCode = $this->getConvenio();
            $arr->clientNumber = $this->getNossoNumero();
            $arr->dueDate = $this->getVencimento()->format('Y-m-d');
            $arr->issueDate = $this->getEmissao()->format('Y-m-d');
            $arr->nominalValue = number_format($this->getValor(), 2, '.', '');;
            $arr->bankNumber = $this->getNossoNumero();

            $arr->paymentType = "REGISTRO";
            $arr->writeOffQuantityDays = $this->getPrazoDevolucao();


            $payer = new stdClass();

            $payer->name = mb_substr(Helper::alfaNumerico($this->pagador->getNome()), 0, 40);
            $payer->documentType = $this->pagador->getTipoDocumento();
            $payer->documentNumber = $this->pagador->getDocumento();
            $payer->address = mb_substr(Helper::alfaNumerico($this->pagador->getLogradouro() . ($this->pagador->getNumero() ? ', ' . $this->pagador->getNumero() : '')), 0, 40);
            $payer->neighborhood = Helper::alfaNumerico($this->pagador->getBairro());
            $payer->city = mb_substr(Helper::alfaNumerico($this->pagador->getCidade()), 0, 20);
            $payer->state = Helper::alfaNumerico($this->pagador->getUf());
            $payer->zipCode = Helper::mask($this->pagador->getCep(), '#####-###');


            $arr->payer = $payer;

            $beneficiary = new stdClass();
            $beneficiary->name = mb_substr(Helper::alfaNumerico($this->beneficiario->getNome()), 0, 40);
            $beneficiary->documentType = $this->beneficiario->getTipoDocumento();
            $beneficiary->documentNumber = $this->beneficiario->getDocumento();

            $arr->beneficiary = $beneficiary;


            if ($this->getGerarPix()) {
                $chavePix = $this->getChavePix();
                $beneficiarioDoc = $this->beneficiario->getDocumento();
                $key = new stdClass();

                if (strlen($chavePix) === 11 && $chavePix === $beneficiarioDoc) {
                    $key->type = "CPF";
                } elseif (strlen($chavePix) === 14 && $chavePix === $beneficiarioDoc) {
                    $key->type = "CNPJ";
                } elseif (filter_var($chavePix, FILTER_VALIDATE_EMAIL)) {
                    $key->type = "EMAIL";
                } elseif (preg_match("/^\(?\d{2}\)?\s?\d{4,5}-?\d{4}$/", $chavePix)) {
                    $key->type = "CELULAR";
                } elseif (strlen($chavePix) === 36) {
                    $key->type = "EVP";
                } else {
                    throw new InvalidArgumentException('Chave PIX inválida.');
                }

                $key->dictKey = $chavePix;
                $arr->key = $key;
            }


            if (count($this->desconto) > 0) {
                if (count($this->desconto) > 3) {
                    throw new InvalidArgumentException('Quantidade desconto informado maior que 3.');
                }
                $discount = new stdClass();
                foreach ($this->desconto as $x => $desconto) {

                    if ($desconto->getTipo() !== $desconto::Valor) {
                        throw new InvalidArgumentException('Tipo de desconto inválido.');
                    }

                    $discount->type = 'VALOR_DATA_FIXA';

                    if ($x === 0) {
                        $discount->discountOne = new stdClass();
                        $discount->discountOne->value = number_format($desconto->getValor(), 2, '.', '');
                        $discount->discountOne->limitDate = $desconto->getData()->format('Y-m-d');
                    } elseif ($x === 1) {
                        $discount->discountTwo = new stdClass();
                        $discount->discountTwo->value = number_format($desconto->getValor(), 2, '.', '');
                        $discount->discountTwo->limitDate = $desconto->getData()->format('Y-m-d');
                    } elseif ($x === 2) {
                        $discount->discountThree = new stdClass();
                        $discount->discountThree->value = number_format($desconto->getValor(), 2, '.', '');
                        $discount->discountThree->limitDate = $desconto->getData()->format('Y-m-d');
                    }
                }
                $arr->discount = $discount;
            }


            if (!empty($this->multa)) {
                $multa = $this->multa;
                $interval_multa = date_diff($this->getVencimento(), $multa->getData());
                $arr->finePercentage = number_format($multa->getPercentual(), 2, '.', ''); // Percentual de multa após vencimento
                $arr->fineQuantityDays = max(0, $interval_multa->format('%a')); // Quantidade de dias após o vencimento, para incidência de multa
            }


            if (!empty($this->juros)) {
                $juros = $this->juros;
                /*
                if ($juros->getTipo() === $this->juros::Isento) {
                    // Isento
                } elseif ($juros->getTipo() === $this->juros::Diario) {
                    $interest = new stdClass();
                    $interest->interestValue = number_format($juros->getValor(), 2, '.', '');
                    $interest->dailyInterestValue = $juros->getData()->format('Y-m-d');
                    $arr->interest = $interest;
                } elseif ($juros->getTipo() === $this->juros::Mensal) {
                    $interest = new stdClass();
                    $interest->interestPercentage = number_format($juros->getValor(), 2, '.', '');
                    $interest->dailyInterestValue = $juros->getData()->format('Y-m-d');
                    $arr->interest = $interest;
                } else {
                    throw new InvalidArgumentException('Código do tipo de juros inválido.');
                }
                */
                if ($juros->getTipo() === $this->juros::Isento) {
                    // Isento
                } elseif ($juros->getTipo() === $this->juros::Mensal) {
                    $arr->interestPercentage = number_format($juros->getValor(), 2, '.', '');
                } else {
                    throw new InvalidArgumentException('Código do tipo de juros inválido.');
                }

            }

            $arr->documentKind = "DUPLICATA_MERCANTIL";

            // Se o beneficiário for igual ao pagador, então é um boleto de depósito de aporte, para isso, não pode ter multa, juros e desconto, somente um desconto, normativa 157158 e 157164
            if($this->beneficiario->getDocumento() === $this->pagador->getDocumento()) {
                throw new \Boleto\Exception\InvalidArgumentException(490, 'Cnpj raiz do pagador nao pode ser igual ao do beneficiario final - usar bda', 400);

                $arr->documentKind = "BOLETO_DEPOSITO_APORTE";

                // Como não pode ter multa, juros e desconto, então não pode pagar após o vencimento
                $arr->writeOffQuantityDays = 0;

                // Remove multa, juros e desconto
                unset($arr->finePercentage);
                unset($arr->fineQuantityDays);
                unset($arr->interestPercentage);
                unset($arr->discount);

                if (count($this->desconto) > 0) {
                    if (count($this->desconto) > 3) {
                        throw new InvalidArgumentException('Quantidade desconto informado maior que 3.');
                    }
                    foreach ($this->desconto as $desconto) {
                        if ($desconto->getTipo() !== $desconto::Valor) {
                            throw new InvalidArgumentException('Tipo de desconto inválido.');
                        }
                        // Se a data do Desconto for igual ao Vencimento, então é um desconto único
                        if($desconto->getData()->format('Y-m-d') === $this->getVencimento()->format('Y-m-d')) {
                            $arr->deductionValue = number_format($desconto->getValor(), 2, '.', '');
                            break;
                        }
                    }
                }
            }

            //$a = json_encode($arr, JSON_PRETTY_PRINT|JSON_PRESERVE_ZERO_FRACTION);

            $token = $this->getToken();

            $headers = [
                'Authorization' => "Bearer $token",
                'X-Application-Key' => $this->getClientId()
            ];

            if ($this->isSandbox()) {
                $endpoint = 'https://trust-sandbox.api.santander.com.br';
                $endpoint = 'https://trust-open-h.api.santander.com.br';
            } else {
                $endpoint = 'https://trust-open.api.santander.com.br';
            }

            $client = new Client(['base_uri' => $endpoint, 'verify' => false]);

            $res = $client->request('POST', '/collection_bill_management/v2/workspaces/' . $this->getWorkspaceId() . '/bank_slips', [
                'headers' => $headers,
                'cert' => $this->getCertificado()->getCertificateFilePem(),
                'json' => $arr
            ]);

            if ($res->getStatusCode() === 201) {

                $body = json_decode($res->getBody()->getContents());
                $linhaDigital = $body->digitableLine;
                $codigoBarras = $body->barCode;
                $pix = $body->qrCodePix;

                $this->setCodigobarras($codigoBarras);
                $this->setLinhadigitavel($linhaDigital);
                $this->setPixQrCode($pix);
            }


        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $error = json_decode($e->getResponse()->getBody()->getContents());

                if (isset($error->_errors)) {
                    foreach ($error->_errors as $err) {
                        throw new \Boleto\Exception\InvalidArgumentException($err->_code, $err->_message, $e->getCode());
                    }
                }

                if (isset($error->statusHttp)) {
                    throw new \Boleto\Exception\InvalidArgumentException(null, $error->errorMessage, $e->getCode());
                }

            }
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws \Boleto\Exception\InvalidArgumentException
     * @throws Exception
     */
    public function baixar(): void
    {
        try {

            $token = $this->getToken();

            $headers = [
                'Authorization' => "Bearer $token",
                'X-Application-Key' => $this->getClientId()
            ];

            if ($this->isSandbox()) {
                $endpoint = 'https://trust-sandbox.api.santander.com.br';
            } else {
                $endpoint = 'https://trust-open.api.santander.com.br';
            }

            $client = new Client(['base_uri' => $endpoint, 'verify' => false]);

            $arr = new stdClass();
            $arr->covenantCode = $this->getConvenio();
            $arr->bankNumber = $this->getNossoNumero();
            $arr->operation = 'BAIXAR';

            $res = $client->request('PATCH', '/collection_bill_management/v2/workspaces/' . $this->getWorkspaceId() . '/bank_slips', [
                'headers' => $headers,
                'cert' => $this->getCertificado()->getCertificateFilePem(),
                'json' => $arr
            ]);

            if ($res->getStatusCode() === 200) {
                $body = json_decode($res->getBody()->getContents());
            }

        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $error = json_decode($e->getResponse()->getBody()->getContents());
                if (isset($error->statusHttp)) {
                    $code = $this->getErrorCode($error->errorMessage);

                    throw new \Boleto\Exception\InvalidArgumentException($code, $error->errorMessage, $e->getCode());
                }
                throw new \Boleto\Exception\InvalidArgumentException($error->code, $error->message, $e->getCode());
            }
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws \Boleto\Exception\InvalidArgumentException
     * @throws GuzzleException
     * @throws Exception
     */
    public function consulta(): void
    {
        try {

            $token = $this->getToken();

            $headers = [
                'Authorization' => "Bearer $token",
                'X-Application-Key' => $this->getClientId()
            ];

            if ($this->isSandbox()) {
                $endpoint = 'https://trust-sandbox.api.santander.com.br';
            } else {
                $endpoint = 'https://trust-open.api.santander.com.br';
            }

            $client = new Client(['base_uri' => $endpoint, 'verify' => false]);

            $arr = new stdClass();
            $arr->covenantCode = $this->getConvenio();
            $arr->bankNumber = $this->getNossoNumero();
            $arr->operation = 'BAIXAR';

            $res = $client->request('GET', '/collection_bill_management/v2/bills/'.$this->getConvenio().'.'.$this->getNossoNumero().'?tipoConsulta=bankslip', [
                'headers' => $headers,
                'cert' => $this->getCertificado()->getCertificateFilePem(),
                'json' => $arr
            ]);

            if ($res->getStatusCode() === 200) {
                $body = json_decode($res->getBody()->getContents());
                $this->setCodigobarras($body->bankSlipData->barCode);
                $this->setLinhadigitavel($body->bankSlipData->digitableLine);
                $this->setPixQrCode(null);
                if(isset($body->qrCodeData)) {
                    $this->setPixQrCode($body->qrCodeData->qrCode);
                }

                $pagador = new Pagador($body->payerData->payerName, $body->payerData->payerDocumentNumber, $body->payerData->payerAddress, null, null, $body->payerData->payerNeighborhood, $body->payerData->payerCounty, $body->payerData->payerStateAbbreviation, $body->payerData->payerZipCode);

                $this->setPagador($pagador);
            }

        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $error = json_decode($e->getResponse()->getBody()->getContents());
                if (isset($error->statusHttp)) {
                    $code = $this->getErrorCode($error->errorMessage);

                    throw new \Boleto\Exception\InvalidArgumentException($code, $error->errorMessage, $e->getCode());
                }
                throw new \Boleto\Exception\InvalidArgumentException($error->code, $error->message, $e->getCode());
            }
            throw new Exception($e->getMessage());
        }
    }


    private function getToken()
    {
        try {
            $key = sha1('boleto-santander' . $this->agencia . $this->getBeneficiario()->getDocumento());
            $item = $this->cache->getItem($key);
            if (!$item->isHit()) {

                if ($this->isSandbox()) {
                    $endpoint = 'https://trust-sandbox.api.santander.com.br';
                    $endpoint = 'https://trust-open-h.api.santander.com.br';
                } else {
                    $endpoint = 'https://trust-open.api.santander.com.br';
                }

                $client = new Client([
                    'base_uri' => $endpoint,
                    'cert' => $this->getCertificado()->getCertificateFilePem(),
                    'verify' => false
                ]);


                $res = $client->request('POST', '/auth/oauth/v2/token', [
                    'form_params' => [
                        'client_id' => $this->getClientId(),
                        'client_secret' => $this->getSecretId(),
                        'grant_type' => 'client_credentials'
                    ]
                ]);




                if ($res->getStatusCode() === 200) {
                    $json = $res->getBody()->getContents();
                    $arr = json_decode($json);
                    $item->set($arr->access_token);
                    $item->expiresAfter($arr->expires_in);
                    $this->cache->saveDeferred($item);
                    return $item->get();
                }
            }
            return $item->get();
        } catch (RequestException|Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        } catch (GuzzleException $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }


    private function normalizeString($string): array|string|null
    {
        $string = mb_strtolower($string); // Converte para minúsculo
        $string = preg_replace('/[áàâãä]/u', 'a', $string);
        $string = preg_replace('/[éèêë]/u', 'e', $string);
        $string = preg_replace('/[íìîï]/u', 'i', $string);
        $string = preg_replace('/[óòôõö]/u', 'o', $string);
        $string = preg_replace('/[úùûü]/u', 'u', $string);
        $string = preg_replace('/[ç]/u', 'c', $string);
        $string = preg_replace('/[ñ]/u', 'n', $string);
        return preg_replace('/[^a-z0-9 ]/u', '', $string);
    }


    public function getCarteira()
    {
        // TODO: Implement getCarteira() method.
    }

    private function tmpfile($name, $content): string
    {
        if (is_null($name)) {
            $name = uniqid("");
        }


        $file = trim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . ltrim($name, DIRECTORY_SEPARATOR);

        file_put_contents($file, $content);

        register_shutdown_function(function () use ($file) {
            @unlink($file);
        });

        return $file;
    }
}
