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
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use stdClass;

class BradescoService extends AbstractBank implements InterfaceBank
{


    private ?DateTime $vencimento;
    private ?DateTime $emissao;
    private ?float $valor;
    private ?int $agencia;
    private ?int $conta;
    private ?string $nossonumero;
    private string $codigobarras;
    private string $linhadigitavel;
    private string $pixqrcode;
    private int $prazodevolucao = 0;
    private bool $pix = true;
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

    /**
     * BradescoService constructor.
     * @param DateTime|null $vencimento
     * @param null $valor
     * @param null $nossonumero
     * @param null $agencia
     * @param null $conta
     * @param Pagador|null $pagador
     * @param Beneficiario|null $beneficiario
     * @param Certificado|null $certificado
     */
    public function __construct(DateTime $vencimento = null, $valor = null, $nossonumero = null, $agencia = null, $conta = null, Pagador $pagador = null, Beneficiario $beneficiario = null, Certificado $certificado = null, $clientId = null)
    {
        $this->cache = \Boleto\Factory\CacheFactory::getCache();

        $this->vencimento = $vencimento;
        $this->valor = $valor;
        $this->nossonumero = $nossonumero;
        $this->agencia = $agencia;
        $this->conta = $conta;
        $this->pagador = $pagador;
        $this->beneficiario = $beneficiario;
        $this->certificado = $certificado;
        $this->clientId = $clientId;

    }

    /**
     * @param DateTime $date
     * @return BradescoService
     */
    public function setEmissao(DateTime $date): BradescoService
    {
        $this->emissao = $date;
        return $this;
    }

    /**
     * @param DateTime $date
     * @return BradescoService
     */
    public function setVencimento(DateTime $date): BradescoService
    {
        $this->vencimento = $date;
        return $this;
    }

    /**
     * @param double $valor
     * @return BradescoService
     */
    public function setValor(float $valor): BradescoService
    {
        $this->valor = $valor;
        return $this;
    }

    /**
     * @param int $nossonumero
     * @return BradescoService
     */
    public function setNossoNumero(int $nossonumero): BradescoService
    {
        $this->nossonumero = $nossonumero;
        return $this;
    }

    /**
     * @param int $agencia
     * @return BradescoService
     */
    public function setAgencia(int $agencia): BradescoService
    {
        $this->agencia = $agencia;
        return $this;
    }

    /**
     * @param int $conta
     * @return BradescoService
     */
    public function setConta(int $conta): BradescoService
    {
        $this->conta = $conta;
        return $this;
    }



    /**
     * @param Pagador|null $pagador
     * @return BradescoService
     */
    public function setPagador(Pagador $pagador = null): BradescoService
    {
        $this->pagador = $pagador;
        return $this;
    }

    /**
     * @param Beneficiario|null $beneficiario
     * @return BradescoService
     */
    public function setBeneficiario(Beneficiario $beneficiario = null): BradescoService
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
     * @return BradescoService
     */
    public function setClientId(string $clientId): BradescoService
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

    /**
     * @param $certificado Certificado
     * @return BradescoService
     */
    public function setCertificado(Certificado $certificado): BradescoService
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

    /**
     * @return Juros
     */
    public function getJuros(): Juros
    {
        return $this->juros;
    }

    /**
     * @param Juros $juros
     * @return BradescoService
     */
    public function setJuros(Juros $juros): BradescoService
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
     * @return BradescoService
     */
    public function setMulta(Multa $multa): BradescoService
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
     * @return BradescoService
     */
    public function setDesconto(Desconto $desconto): BradescoService
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
     * @return BradescoService
     */
    public function setPixQrCode(?string $pixqrcode): BradescoService
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
     * @return BradescoService
     */
    public function setSandbox(bool $sandbox): BradescoService
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
     * @return BradescoService
     */
    public function setPrazoDevolucao(int $prazodevolucao): BradescoService
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
     * @return BradescoService
     */
    public function setGerarPix(bool $pix): BradescoService
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

			if((string)$this->getNossoNumero() === '0') {
				throw new \Boleto\Exception\InvalidArgumentException(99999, 'Nosso Numero Invalido', 400);
			}

            $arr = new stdClass();
            $arr->ctitloCobrCdent = (string)$this->getNossoNumero(); // Nosso Número
            $arr->ctitloCliCdent = $this->getNossoNumero(); // Identificador do título pelo beneficiário (Seu Número)

            $arr->nroCpfCnpjBenef = $this->beneficiario->getDocumentoRaiz(); //Número de Inscrição do CNPJ ou CPF do Beneficiário
            $arr->filCpfCnpjBenef = $this->beneficiario->getDocumentoFilial(); // Número da Filial do CNPJ do Beneficiário
            $arr->digCpfCnpjBenef = $this->beneficiario->getDocumentoControle(); // Dígitos verificadores do CNPJ ou CPF do Beneficiário


            $arr->demisTitloCobr = $this->getEmissao()->format('d.m.Y'); // Data de emissão do título
            $arr->dvctoTitloCobr = $this->getVencimento()->format('d.m.Y'); // Data de vencimento do título

            $arr->cnegocCobr = $this->getNumeroNegociacao(); // Número do Contrato (Negociação Agência + Conta)
            $arr->vnmnalTitloCobr = (string)number_format($this->getValor(), 2, '', ''); // Valor nominal do título

            $prazo = $this->getPrazoDevolucao();
            $dataLimite = clone $this->getVencimento();
            $dataLimite->modify("+{$prazo} days");

            $arr->validadeAposVencimento = $prazo; // Quantidade de dias após vencimento, que o título é válido para pagamento via Pix
            $arr->dataLimitePgt10 = $dataLimite->format('d.m.Y'); // Data-limite para pagamento do título
            $arr->dataPerm10 = $dataLimite->format('d.m.Y'); // Data da comissão de permanência após vencimento (juros)

            $arr->registrarTitulo = 1; // Registrar o título
            $arr->codUsuario = "APISERVIC"; // FIXO, Código do Usuário responsável

            $arr->tipoAcesso = 2; // FIXO, Tipo de acesso desejado
            $arr->cidtfdProdCobr = 9; // Identificador do Produto Cobrança
            $arr->codigoBanco = 237; // FIXO, Código do Banco
            $arr->tipoRegistro = 1; // Tipo de registro do título
            $arr->cidtfdTpoVcto = 0; // FIXO, Identificador do tipo de vencimento
            $arr->cespceTitloCobr = 99; // Código da espécie do título
            $arr->cindcdAceitSacdo = "N"; // FIXO, Identificador de aceite do devedor
            $arr->fase = 1; // Registro do título e geração do QR Code.
            $arr->cindcdCobrMisto = $this->getGerarPix() ? 'S' : 'N'; // Indicador do registro de título com QR Code
            $arr->cformaEmisPplta = 2; //  Forma de emissão do boleto (Papeleta)
            $arr->cindcdPgtoParcial = "N"; // Indicador de pagamento parcial
            $arr->qtdePgtoParcial = 0; // FIXO, Quantidade de pagamento parcial de 001 a 099

            if (!empty($this->juros)) {
                $juros = $this->juros;
                $interval_juros = date_diff($this->getVencimento(), $juros->getData());
                if ($juros->getTipo() === $this->juros::Isento) {
                    // Isento
                } elseif ($juros->getTipo() === $this->juros::Diario) {
                    $arr->vdiaJuroMora = number_format($juros->getValor(), 2, '', ''); // Valor diário de juros após vencimento,
                    $arr->qdiaInicJuro = max(1, $interval_juros->format('%a')); // Quantidade de dias após o vencimento, para incidência de juros
                } elseif ($juros->getTipo() === $this->juros::Mensal) {
                    $arr->ptxJuroVcto = number_format($juros->getValor(), 5, '.', ''); // Percentual de juros após vencimento
                    $arr->qdiaInicJuro = max(1, $interval_juros->format('%a')); // Quantidade de dias após o vencimento, para incidência de juros
                } else {
                    throw new InvalidArgumentException('Código do tipo de juros inválido.');
                }
            }


            if (!empty($this->multa)) {
                $multa = $this->multa;
                $interval_multa = date_diff($this->getVencimento(), $multa->getData());
                $arr->pmultaAplicVcto = str_pad(number_format($multa->getPercentual(), 5, '.', ''), 8, "0", STR_PAD_LEFT); // Percentual de multa após vencimento
                $arr->qdiaInicMulta = max(1, $interval_multa->format('%a')); // Quantidade de dias após o vencimento, para incidência de multa
            }


            if (count($this->desconto) > 0) {
                if (count($this->desconto) > 3) {
                    throw new InvalidArgumentException('Quantidade desconto informado maior que 3.');
                }
                foreach ($this->desconto as $x => $desconto) {
                    if ($desconto->getTipo() === $desconto::Valor) {
                        $arr->{'dlimDescBonif' . ($x + 1)} = $desconto->getData()->format('d.m.Y'); // Data-limite de desconto
                        $arr->{'vdescBonifPgto0' . ($x + 1)} = number_format($desconto->getValor(), 2, '', ''); // Valor de desconto
                    } elseif ($desconto->getTipo() === $desconto::Percentual) {
                        $arr->{'dlimDescBonif' . ($x + 1)} = $desconto->getData()->format('d.m.Y'); // Data-limite de desconto
                        $arr->{'pdescBonifPgto0' . ($x + 1)} = str_pad(number_format($desconto->getValor(), 5, '', ''), 8, "0", STR_PAD_LEFT); // Percentual de desconto
                    } else {
                        throw new InvalidArgumentException('Código do tipo de desconto inválido.');
                    }
                }
            }


            $arr->isacdoTitloCobr = Helper::substr(Helper::ascii($this->pagador->getNome()), 0, 70); // Nome do devedor (Sacado)
            $arr->elogdrSacdoTitlo = Helper::substr(Helper::ascii($this->pagador->getLogradouro()), 0, 40); // Logradouro do devedor (Sacado)
            $arr->enroLogdrSacdo = (int)$this->pagador->getNumero(); // Número do logradouro do devedor
            $arr->ecomplLogdrSacdo = Helper::substr(Helper::ascii($this->pagador->getComplemento()), 0, 15); // Complemento do logradouro do devedor (Sacado)
            $arr->ccepSacdoTitlo = $this->pagador->getCepPrefixo(); // CEP do devedor (Sacado)
            $arr->ccomplCepSacdo = $this->pagador->getCepSufixo(); // Complemento do CEP do devedor (Sacado)
            $arr->ebairoLogdrSacdo = Helper::substr(Helper::ascii($this->pagador->getBairro()), 0, 40); // Bairro do logradouro do devedor (Sacado)
            $arr->imunSacdoTitlo = Helper::substr(Helper::ascii($this->pagador->getCidade()), 0, 30); // Município do devedor (Sacado)
            $arr->csglUfSacdo = Helper::substr(Helper::ascii($this->pagador->getUf()), 0, 2); // Sigla da Unidade Federativa do devedor (Sacado)
            $arr->indCpfCnpjSacdo = $this->pagador->getTipoDocumento() === 'CPF' ? 1 : 2; // Indicador de CPF ou CNPJ do devedor
            $arr->nroCpfCnpjSacdo = Helper::number($this->pagador->getDocumento()); // Número do CPF ou CNPJ do devedor (Sacado)
            $arr->renderEletrSacdo = Helper::substr(Helper::ascii($this->pagador->getEmail()), 0, 70); // Endereço eletrônico do devedor - e-mail (Sacado)

            $now = new DateTime();

            $token = $this->getToken();

            if(empty($token)) {
                throw new Exception("Token inválido.");
            }


            $text = 'POST' . PHP_EOL;
            $text .= '/v1/boleto-hibrido/registrar-boleto' . PHP_EOL . PHP_EOL;
            $text .= json_encode($arr) . PHP_EOL;
            $text .= $token . PHP_EOL;
            $text .= $now->getTimestamp() . '000' . PHP_EOL;
            $text .= $now->format('Y-m-d\TH:i:sP') . PHP_EOL;
            $text .= 'SHA256';

            $text = str_replace(PHP_EOL, "\n", $text);


            $signature = '';
            if (!openssl_sign($text, $signature, $this->certificado->getPrivKey(), OPENSSL_ALGO_SHA256)) {
                throw new Exception("Erro ao gerar a assinatura.");
            }

            $signature = base64_encode($signature);
            $signature = strtr(rtrim($signature, '='), '+/', '-_');

            $headers = [
                'Authorization' => "Bearer $token",
                'X-Brad-Signature' => $signature,
                'X-Brad-Nonce' => $now->getTimestamp() . '000',
                'X-Brad-Timestamp' => $now->format('Y-m-d\TH:i:sP'),
                'X-Brad-Algorithm' => 'SHA256',
                'cpf-cnpj' => Helper::number($this->beneficiario->getDocumento()),
                'access-token' => $this->getClientId()
            ];

            if ($this->isSandbox()) {
                $endpoint = 'https://proxy.api.prebanco.com.br';
            } else {
                $endpoint = 'https://openapi.bradesco.com.br';
            }

            //$client = \Boleto\Factory\ClientFactory::getClient($endpoint);
            $client = new Client(['base_uri' => $endpoint, 'verify' => false]);

            $res = $client->request('POST', '/v1/boleto-hibrido/registrar-boleto', [
                'headers' => $headers,
                'json' => $arr
            ]);


            if ($res->getStatusCode() === 200) {

                $body = json_decode($res->getBody()->getContents());
                $linhaDigital = $body->linhaDig10;
                $codigoBarras = $this->convertLinhaDigitalCodigoBarras($linhaDigital);
                $pix = $body->wqrcdPdraoMercd;

                $this->setCodigobarras($codigoBarras);
                $this->setLinhadigitavel($linhaDigital);
                $this->setPixQrCode($pix);
            }


        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $error = json_decode($e->getResponse()->getBody()->getContents());
                if (isset($error->statusHttp)) {
                    $code = $this->getErrorCode($error->errorMessage) ?: crc32(trim($error->errorMessage));
                    throw new \Boleto\Exception\InvalidArgumentException($code, $error->errorMessage, $e->getCode());
                }
                //throw new \Boleto\Exception\InvalidArgumentException($error->code, $error->message, $e->getCode());
            }
            if($e->getCode() === 401){
                throw new \Boleto\Exception\InvalidArgumentException(-100, 'Token inválido', $e->getCode());
            }
            //file_put_contents( ROOT_PATH . '/tmp/bradesco/-'.$this->getNossoNumero().'-response-errorrrr-xxxxx.json', $e->getMessage());
            throw new Exception($e->getMessage());
        }
    }


    private function getToken()
    {
        try {
            $key = sha1('boleto-bradesco' . $this->agencia . $this->getBeneficiario()->getDocumento());
            $item = $this->cache->getItem($key);
            if (!$item->isHit()) {

                $time = time();

                $payload = [
                    'aud' => ($this->isSandbox() ? 'https://proxy.api.prebanco.com.br' : 'https://openapi.bradesco.com.br') . ($this->isSandbox() ? '/auth/server/v1.2/token' : '/auth/server/v1.1/token'),
                    'sub' => $this->getClientId(),
                    'iat' => $time,
                    'exp' => $time + 3600,
                    'jti' => $time . '000',
                    'ver' => '1.1'
                ];

                $jwt = JWT::encode($payload, $this->certificado->getPrivKey(), 'RS256');


                if ($this->isSandbox()) {
                    $endpoint = 'https://proxy.api.prebanco.com.br';
                } else {
                    $endpoint = 'https://openapi.bradesco.com.br';
                }

                $client = new Client(['base_uri' => $endpoint, 'verify' => false]);

                $res = $client->request('POST', $this->isSandbox() ? '/auth/server/v1.2/token' : '/auth/server/v1.1/token', [
                    'form_params' => [
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $jwt
                    ]
                ]);

                if ($res->getStatusCode() === 200) {
                    $json = $res->getBody()->getContents();
                    $arr = json_decode($json);
                    //$arr->iat = $time;
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
}
