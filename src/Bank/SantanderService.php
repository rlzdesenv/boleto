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
    private string $pixqrcode;
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
        $this->cache = new ApcuCachePool();

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
            $arr->documentKind = "DUPLICATA_MERCANTIL";
            // $arr->deductionValue = 0;
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
                $arr->fineQuantityDays = max(1, $interval_multa->format('%a')); // Quantidade de dias após o vencimento, para incidência de multa
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
            $arr->covenantCode = $this->getNossoNumero();
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


    private function getToken()
    {
        try {
            $key = sha1('boleto-santander' . $this->agencia . $this->getBeneficiario()->getDocumento());
            $item = $this->cache->getItem($key);
            if (!$item->isHit()) {

                $time = time();

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
                    $item->expiresAfter($time + $arr->expires_in);
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

    private function getErrorCode($errorMessage): ?int
    {
        $errors = [
            -99 => 'Serviço indisponível no momento. Tente novamente mais tarde.',
            -4 => 'Tamanho do campo inválido ',
            -3 => 'Tipo do campo inválido',
            -2 => 'Contrato não encontrado ',
            -1 => 'Contrato não aprovado',
            0 => 'Solicitação atendida ',
            1 => 'Solicitação não encontrada',
            2 => 'Erro Genérico - sistema indisponível',
            5 => 'Inclusão efetuada',
            6 => 'Dados inconsistentes',
            10 => 'Erro Acesso Sub-rotina',
            12 => 'Cliente/Negociação Bloqueado',
            13 => 'Usuário não Autorizado',
            14 => 'Espécie Título Inválida',
            15 => 'Tipo/Número Inscrição Inválido',
            16 => 'Informe todos os campos para decurso de Prazo',
            17 => 'Nome do Pagador Especial não Informado',
            18 => 'Endereço Inválido',
            19 => 'CEP Inválido',
            20 => 'Agência Depositária Inválida',
            21 => 'Informe todos os campos para Instrução de Protesto',
            22 => 'Banco Inválido',
            23 => 'Seu Número Inválido',
            24 => 'Informe todos os campos para Abatimento',
            25 => 'Valor dos Juros maior que o Valor do Título',
            26 => 'Data de Emissão maior que a Data de Vencimento',
            27 => 'Documento do Sacador Avalista Inválido',
            28 => 'Informe todos os campos para Desconto',
            29 => 'Informe todos os campos para Sacador Avalista',
            30 => 'Data Vencimento menor ou igual Data Emissão',
            31 => 'Data Desconto menor ou igual Data Emissão',
            32 => 'Data Desconto maior que Data Vencimento',
            33 => 'Valor Desconto/Bonificação maior ou igual Valor Título',
            34 => 'Tipo informado deve ser 1, 2 ou 3',
            35 => 'Valor Abatimento maior que o Valor do Título',
            36 => 'CEP Inválido',
            37 => 'Data Emissão Inválida',
            38 => 'Data Vencimento Inválida',
            39 => 'Percentual informado maior ou igual 100,00',
            40 => 'Número CGC/CPF inválido',
            41 => 'Protesto Automático x Decurso de Prazo Incompatível',
            42 => 'Banco/Agência Depositária Inválido',
            43 => 'Espécie de Documento inválido',
            44 => 'Informe 1-Contra-apresentação ou 2-À vista',
            45 => 'Código da instrução de protesto inválido',
            46 => 'Dias para instrução de protesto inválido',
            47 => 'Código para desconto inválido',
            48 => 'Código para multa inválido',
            49 => 'Código para comissão permanência dia inválido',
            50 => 'Espécie Documento exige CGC para Sacador Avalista',
            51 => 'CEP e/ou Banco/Agência Depositária Inválido',
            52 => 'Data Emissão maior ou igual Data Vencimento',
            53 => 'Data Desconto Inválida',
            54 => 'Data emissão maior Data Registro',
            55 => 'Percentual multa informado maior que o permitido',
            56 => 'Percentual comissão permanência informado maior que o permitido',
            57 => 'Percentual Bonificação informado maior que o permitido',
            58 => 'Prazo para Protesto inválido 59 Informe a data ou tipo do vencimento',
            60 => 'Valor do IOF não permitido para produtos 05,15,43 ou 44',
            61 => 'Abatimento já cadastrado para o título',
            62 => 'Abatimento não',
            65 => 'Negociação inexistente',
            66 => 'Cliente inexistente ',
            67 => 'CNPJ/CPF inválido',
            68 => 'N. Número não pode ser informado quando status 4',
            69 => 'Título já cadastrado',
            70 => 'Data e tipo de vencimento incompatíveis',
            71 => 'Data de vencimento não pode ser posterior a 10 anos',
            72 => 'Dias para instrução inferior ao padrão',
            73 => 'Dias para instrução antecipa data de protesto',
            74 => 'Valor IOF obrigatório',
            75 => 'Valor IOF incompatível com Id produto',
            76 => 'Tipo de abatimento inválido',
            77 => 'Status Inválido',
            78 => 'Registro on-line não permite Banco diferente de 237',
            79 => 'Carta para protesto não recebida',
            80 => 'Tipo de vencimento inválido',
            81 => 'Valor acumulado desconto/bonificação maior ou igual valor título',
            82 => 'Datas desconto/bonificação fora de sequência',
            83 => 'Informe todos os campos para multa',
            84 => 'Código comissão permanência inválido',
            85 => 'Informe todos os campos para comissão permanência',
            86 => 'Registro duplicado na tabela de ocorrências',
            87 => 'Solicitação de protesto já existente',
            88 => 'Registro duplicado na base de atualização sequencial',
            89 => 'Sacador avalista já cadastrado',
            90 => 'Indicador CIP inexistente',
            91 => 'Moeda negociada inexistente',
            92 => 'Banco/Agência operadora inexistente',
            93 => 'Acessório escritural negociado inexistente',
            94 => 'Polo de serviço inexistente para Banco/Agência',
            95 => 'Banco/Agência centralizadora não cadastrada para Banco/Agência depositária',
            96 => 'Título não encontrado pelo módulo CBON8230',
            97 => 'Valor IOF maior ou igual valor título',
            98 => 'Data Inválida',
            99 => 'Id Prod/Cta não cadastrados'
        ];

        $name = $this->normalizeString($errorMessage); // Normaliza a string de entrada
        $bestMatchId = null;
        $highestSimilarity = 0;

        foreach ($errors as $id => $errorMessage) {
            $normalizedMessage = $this->normalizeString($errorMessage);

            // Verifica se o nome corresponde exatamente
            if (strcasecmp($normalizedMessage, $name) === 0) {
                return $id;
            }

            // Verifica a semelhança entre as strings
            similar_text($normalizedMessage, $name, $percent);
            if ($percent > $highestSimilarity) {
                $highestSimilarity = $percent;
                $bestMatchId = $id;
            }
        }

        // Retorna o ID da melhor correspondência (ou null se nenhuma foi encontrada)
        return $highestSimilarity > 70 ? $bestMatchId : null;

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
