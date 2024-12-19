<?php
/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 03/07/2017
 * Time: 09:05
 */

namespace Boleto\Bank;

use Boleto\Entity\Beneficiario;
use Boleto\Entity\Desconto;
use Boleto\Entity\Juros;
use Boleto\Entity\Multa;
use Boleto\Entity\Pagador;
use Boleto\Exception\InvalidArgumentException;
use Boleto\Helper\Helper;
use Cache\Adapter\Apcu\ApcuCachePool;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use SimpleXMLElement;
use SoapClient;
use SoapFault;

class BrasilService implements InterfaceBank
{


    /**
     * @var Datetime
     */
    private $vencimento, $emissao, $databaixa;
    private $valor;
    private $convenio;
    private $variacaocarteira;
    private $nossonumero;
    private $carteira;
    private $codigobarras;
    private $linhadigitavel;
    private $pixqrcode;
    private $prazodevolucao;
    private $pix = true;

    /**
     * @var Pagador
     */
    private $pagador;

    /**
     * @var Beneficiario
     */
    private $beneficiario;

    /**
     * @var Juros
     */
    private $juros;

    /**
     * @var Multa
     */
    private $multa;

    /**
     * @var Desconto[]
     */
    private $desconto = [];

    private $clientId;
    private $secretId;
    private $cache;

    private $sandbox = false;

    private $client = 'WEBSERVICE';
    private $appKey = null;


    /**
     * BrasilService constructor.
     * @param DateTime|null $vencimento
     * @param null $valor
     * @param null $nossonumero
     * @param null $carteira
     * @param null $convenio
     * @param null $variacaocarteira
     * @param Pagador|null $pagador
     * @param null $clientId
     * @param null $secredId
     * @throws Exception
     */
    public function __construct(Datetime $vencimento = null, $valor = null, $nossonumero = null, $carteira = null, $convenio = null, $variacaocarteira = null, Pagador $pagador = null, $clientId = null, $secredId = null)
    {
        $this->cache = new ApcuCachePool();

        $this->emissao = new Datetime();
        $this->vencimento = $vencimento;
        $this->valor = $valor;
        $this->nossonumero = $nossonumero;
        $this->carteira = $carteira;
        $this->convenio = $convenio;
        $this->variacaocarteira = $variacaocarteira;
        $this->pagador = $pagador;
        $this->clientId = $clientId;
        $this->secretId = $secredId;
    }

    /**
     * @param Datetime $date
     * @return BrasilService
     */
    public function setEmissao(Datetime $date)
    {
        $this->emissao = $date;
        return $this;
    }

    /**
     * @param Datetime $date
     * @return BrasilService
     */
    public function setVencimento(Datetime $date)
    {
        $this->vencimento = $date;
        return $this;
    }

    /**
     * @param double $valor
     * @return BrasilService
     */
    public function setValor($valor)
    {
        $this->valor = $valor;
        return $this;
    }

    /**
     * @param int $nossonumero
     * @return BrasilService
     */
    public function setNossoNumero($nossonumero)
    {
        $this->nossonumero = $nossonumero;
        return $this;
    }

    /**
     * @param int $convenio
     * @return BrasilService
     */
    public function setConvenio($convenio)
    {
        $this->convenio = $convenio;
        return $this;
    }

    /**
     * @param int $variacaocarteira
     * @return BrasilService
     */
    public function setVariacaoCarteira($variacaocarteira)
    {
        $this->variacaocarteira = $variacaocarteira;
        return $this;
    }

    /**
     * @param int $carteira
     * @return BrasilService
     */
    public function setCarteira($carteira)
    {
        $this->carteira = $carteira;
        return $this;
    }

    /**
     * @param Pagador $pagador
     * @return BrasilService
     */
    public function setPagador(Pagador $pagador = null)
    {
        $this->pagador = $pagador;
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
     * @param Beneficiario $beneficiario
     * @return BrasilService
     */
    public function setBeneficiario(Beneficiario $beneficiario): BrasilService
    {
        $this->beneficiario = $beneficiario;
        return $this;
    }

    /**
     * @param string $clientId
     * @return BrasilService
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * @param string $clientId
     * @return BrasilService
     */
    public function setSecretId($secretId)
    {
        $this->secretId = $secretId;
        return $this;
    }

    /**
     * @return string
     */
    private function getClientId()
    {
        if (is_null($this->clientId)) {
            throw new \InvalidArgumentException('O parâmetro clientId nulo.');
        }
        return $this->clientId;
    }

    /**
     * @return string
     */
    private function getSecretId()
    {
        if (is_null($this->clientId)) {
            throw new \InvalidArgumentException('O parâmetro secretId nulo.');
        }
        return $this->secretId;
    }

    /**
     * @param string $codigobarras
     */
    private function setCodigobarras($codigobarras)
    {
        $this->codigobarras = $codigobarras;
    }

    /**
     * @param string $linhadigitavel
     */
    private function setLinhadigitavel($linhadigitavel)
    {
        $this->linhadigitavel = $linhadigitavel;
    }

    /**
     * @param Datetime
     */
    public function getEmissao()
    {
        if (is_null($this->emissao)) {
            throw new \InvalidArgumentException('Data Emissäo inválido.');
        }
        return $this->emissao;
    }

    /**
     * @param Datetime
     */
    public function getVencimento()
    {
        if (is_null($this->vencimento)) {
            throw new \InvalidArgumentException('Data Vencimento inválido.');
        }
        return $this->vencimento;
    }

    /**
     * @return int
     */
    public function getCarteira()
    {
        if (is_null($this->carteira)) {
            throw new \InvalidArgumentException('Carteira inválido.');
        }
        return $this->carteira;
    }

    /**
     * @return double
     */
    public function getValor()
    {
        if (is_null($this->valor)) {
            throw new \InvalidArgumentException('Valor inválido.');
        }
        return $this->valor;
    }

    /**
     * @return string
     */
    public function getNossoNumero()
    {
        if (is_null($this->nossonumero)) {
            throw new \InvalidArgumentException('Nosso Numero inválido.');
        }
        return $this->nossonumero;
    }

    /**
     * @return string
     */
    public function getLinhaDigitavel()
    {
        return $this->linhadigitavel;
    }

    /**
     * @return string
     */
    public function getCodigoBarras()
    {
        return $this->codigobarras;
    }

    /**
     * @return string
     */
    private function getConvenio()
    {
        if (is_null($this->convenio)) {
            throw new \InvalidArgumentException('Convênio inválido.');
        }
        return $this->convenio;
    }

    /**
     * @return string
     */
    private function getVariacaCarteira()
    {
        if (is_null($this->variacaocarteira)) {
            throw new \InvalidArgumentException('Variação Carteira inválido.');
        }
        return $this->variacaocarteira;
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
     * @return BrasilService
     */
    public function setJuros(Juros $juros): BrasilService
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
     * @return BrasilService
     */
    public function setMulta(Multa $multa): BrasilService
    {
        $this->multa = $multa;
        return $this;
    }

    /**
     * @return Desconto[]
     */
    public function getDesconto(): Desconto
    {
        return $this->desconto;
    }

    /**
     * @param Desconto $desconto
     * @return BrasilService
     */
    public function setDesconto(Desconto $desconto): BrasilService
    {
        array_push($this->desconto, $desconto);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPixQrCode(): ?string
    {
        return $this->pixqrcode;
    }

    /**
     * @param mixed $pixqrcode
     * @return BrasilService
     */
    public function setPixQrCode($pixqrcode)
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
     * @return BrasilService
     */
    public function setSandbox(bool $sandbox): BrasilService
    {
        $this->sandbox = $sandbox;
        return $this;
    }

    /**
     * @return int
     */
    public function getPrazoDevolucao()
    {
        return $this->prazodevolucao ?: 0;
    }

    /**
     * @param mixed $prazodevolucao
     * @return CaixaService
     */
    public function setPrazoDevolucao(int $prazodevolucao)
    {
        $this->prazodevolucao = $prazodevolucao;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getGerarPix()
    {
        return this->píx;
    }

    /**
     * @param boolean $prix
     * @return CaixaService
     */
    public function setGerarPix(bool $pix)
    {
        $this->pix = $pix;
        return $this;
    }


    public function send()
    {
        if ($this->getClient() === 'API') {
            $this->sendApi();
        } else {
            try {
                $token = $this->getToken();

                $httpHeaders = [
                    'http' => [
                        'protocol_version' => 1.1,
                        'header' => "Authorization: Bearer " . $token . "\r\n" . "Cache-Control: no-cache"
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];

                $context = stream_context_create($httpHeaders);

                if ($this->isSandbox()) {
                    /* Problema de SSL no Endpoint
                    $endpoint = 'https://cobranca.homologa.bb.com.br:7101/Processos/Ws/RegistroCobrancaService.serviceagent?wsdl';
                    */
                    $endpoint = dirname(__FILE__) . '/../XSD/Banco do Brasil/RegistroCobrancaServiceHomologacao.xml';
                } else {
                    /* Problema de SSL no Endpoint
                    $endpoint = 'https://cobranca.bb.com.br:7101/Processos/Ws/RegistroCobrancaService.serviceagent?wsdl';
                    */
                    $endpoint = dirname(__FILE__) . '/../XSD/Banco do Brasil/RegistroCobrancaService.xml';
                }

                $client = new SoapClient($endpoint,
                    [
                        'trace' => TRUE,
                        'exceptions' => TRUE,
                        'encoding' => 'UTF-8',
                        'compression' => \SOAP_COMPRESSION_ACCEPT | \SOAP_COMPRESSION_GZIP,
                        'cache_wsdl' => WSDL_CACHE_NONE,
                        'connection_timeout' => 30,
                        'stream_context' => $context
                    ]
                );

                $titulo = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Message/>');

                $titulo->addChild('numeroConvenio', $this->getConvenio());
                $titulo->addChild('numeroCarteira', $this->getCarteira());
                $titulo->addChild('numeroVariacaoCarteira', $this->getVariacaCarteira());

                $titulo->addChild('codigoModalidadeTitulo', 1);
                $titulo->addChild('dataEmissaoTitulo', $this->getEmissao()->format('d.m.Y'));
                $titulo->addChild('dataVencimentoTitulo', $this->getVencimento()->format('d.m.Y'));
                $titulo->addChild('valorOriginalTitulo', $this->getValor());


                if (count($this->desconto) > 0) {
                    if (count($this->desconto) > 1) {
                        throw new \InvalidArgumentException('Quantidade desconto informado maior que 1.');
                    }
                    foreach ($this->desconto as $desconto) {
                        if ($desconto->getTipo() === $desconto::Valor) {
                            $titulo->addChild('codigoTipoDesconto', '1');
                            $titulo->addChild('dataDescontoTitulo', $desconto->getData()->format('d.m.Y'));
                            $titulo->addChild('valorDescontoTitulo', $desconto->getValor());
                        } elseif ($desconto->getTipo() === $desconto::Percentual) {
                            $titulo = $titulo->addChild('codigoTipoDesconto', '2');
                            $titulo->addChild('dataDescontoTitulo', $desconto->getData()->format('d.m.Y'));
                            $titulo->addChild('percentualDescontoTitulo', $desconto->getValor());
                        } else {
                            throw new \InvalidArgumentException('Código do tipo de desconto inválido.');
                        }
                    }
                } else {
                    $titulo->addChild('codigoTipoDesconto', '');
                }


                $multa = $this->multa;
                if (!is_null($this->multa)) {
                    $titulo->addChild('codigoTipoMulta', 2);
                    $titulo->addChild('percentualMultaTitulo', $multa->getPercentual());
                    $titulo->addChild('dataMultaTitulo', $multa->getData()->format('d.m.Y'));
                } else {
                    $titulo->addChild('codigoTipoMulta', 0);
                }


                $juros = $this->juros;
                if (!is_null($this->juros)) {
                    if ($juros->getTipo() === $this->juros::Isento) {
                        $titulo->addChild('codigoTipoJuroMora', 0);
                    } elseif ($juros->getTipo() === $this->juros::Diario) {
                        $titulo->addChild('codigoTipoJuroMora', 1);
                        $titulo->addChild('valorJuroMoraTitulo', $juros->getValor());
                    } elseif ($juros->getTipo() === $this->juros::Mensal) {
                        $titulo->addChild('codigoTipoJuroMora', 2);
                        $titulo->addChild('percentualJuroMoraTitulo', $juros->getValor());
                    } else {
                        throw new \InvalidArgumentException('Código do tipo de juros inválido.');
                    }
                } else {
                    $titulo->addChild('codigoTipoJuroMora', 0);
                }

                $titulo->addChild('codigoAceiteTitulo', 'N');
                $titulo->addChild('codigoTipoTitulo', 99);

                $titulo->addChild('indicadorPermissaoRecebimentoParcial', 'N');
                $nossonumero = '000' . str_pad($this->getConvenio(), 7, '0') . str_pad($this->getNossoNumero(), 10, '0', STR_PAD_LEFT);
                $titulo->addChild('textoNumeroTituloCliente', $nossonumero);

                $titulo->addChild('codigoTipoInscricaoPagador', $this->pagador->getTipoDocumento() === 'CPF' ? 1 : 2);
                $titulo->addChild('numeroInscricaoPagador', $this->pagador->getDocumento());
                $titulo->addChild('nomePagador', substr(Helper::ascii($this->pagador->getNome()), 0, 60));
                $titulo->addChild('textoEnderecoPagador', substr(Helper::ascii($this->pagador->getLogradouro() . ' ' . $this->pagador->getNumero()), 0, 60));
                $titulo->addChild('numeroCepPagador', substr(Helper::number($this->pagador->getCep()), 0, 8));
                $titulo->addChild('nomeMunicipioPagador', substr(Helper::ascii($this->pagador->getCidade()), 0, 20));
                $titulo->addChild('nomeBairroPagador', substr(Helper::ascii($this->pagador->getBairro()), 0, 20));
                $titulo->addChild('siglaUfPagador', $this->pagador->getUf());
                $titulo->addChild('textoNumeroTelefonePagador', $this->pagador->getTelefone());

                $titulo->addChild('codigoChaveUsuario', 'J1234567');
                $titulo->addChild('codigoTipoCanalSolicitacao', 5);

                $result = $client->__soapCall("RegistroTituloCobranca", [$titulo]);

                if ($result->codigoRetornoPrograma !== 0) {
                    throw new InvalidArgumentException($result->nomeProgramaErro, trim($result->textoMensagemErro));
                }

                $this->setCodigobarras($result->codigoBarraNumerico);
                $this->setLinhadigitavel($result->linhaDigitavel);
            } catch (SoapFault $sf) {
                throw new Exception($sf->faultstring, 500);
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), 500, $e);
            }
        }
    }


    private function sendApi()
    {
        try {
            $boleto = new \stdClass();
            $boleto->numeroConvenio = $this->getConvenio();
            $boleto->numeroCarteira = $this->getCarteira();
            $boleto->numeroVariacaoCarteira = $this->getVariacaCarteira();
            $boleto->codigoModalidade = 0;
            $boleto->dataEmissao = $this->getEmissao()->format('d.m.Y');
            $boleto->dataVencimento = $this->getVencimento()->format('d.m.Y');
            $boleto->valorOriginal = $this->getValor();
            $boleto->valorAbatimento = 0;
            $boleto->quantidadeDiasProtesto = 0;
            $boleto->quantidadeDiasNegativacao = 0;
            $boleto->orgaoNegativador = 0;
            $boleto->indicadorAceiteTituloVencido = $this->getPrazoDevolucao() > 0 ? 'S' : 'N';
            $boleto->numeroDiasLimiteRecebimento = $this->getPrazoDevolucao();
            $boleto->codigoAceite = 'N';
            $boleto->codigoTipoTitulo = 0;
            $boleto->descricaoTipoTitulo = '';
            $boleto->indicadorPermissaoRecebimentoParcial = 'N';
            $boleto->numeroTituloBeneficiario = $this->getNossoNumero();
            $boleto->campoUtilizacaoBeneficiario = '';
            $boleto->numeroTituloCliente = $nossonumero = '000' . str_pad($this->getConvenio(), 7, '0') . str_pad($this->getNossoNumero(), 10, '0', STR_PAD_LEFT);
            $boleto->mensagemBloquetoOcorrencia = '';

            $desconto = new \stdClass();
            $desconto->tipo = 0;
            $boleto->desconto = $desconto;
            $boleto->segundoDesconto = $desconto;
            $boleto->terceiroDesconto = $desconto;

            // DESCONTOS
            if (count($this->desconto) > 0) {
                if (count($this->desconto) > 3) {
                    throw new \InvalidArgumentException('Quantidade desconto informado maior que 3.');
                }
                foreach ($this->desconto as $key => $value) {
                    $desconto = new \stdClass();
                    if ($value->getTipo() === $value::Valor) {
                        $desconto->tipo = 1;
                        $desconto->valor = $value->getValor();
                        $desconto->dataExpiracao = $value->getData()->format('d.m.Y');
                    } elseif ($value->getTipo() === $value::Percentual) {
                        $desconto->tipo = 2;
                        $desconto->porcentagem = $value->getValor();
                        $desconto->dataExpiracao = $value->getData()->format('d.m.Y');
                    } else {
                        throw new \InvalidArgumentException('Código do tipo de desconto inválido.');
                    }

                    if ($key === 0) {
                        $boleto->desconto = $desconto;
                    } elseif ($key === 1) {
                        $boleto->segundoDesconto = $desconto;
                    } elseif ($key === 2) {
                        $boleto->terceiroDesconto = $desconto;
                    }
                }
            }

            // JUROS
            if (!is_null($this->juros) ) {
                $jurosMora = new \stdClass();
                if ($this->juros->getTipo() === $this->juros::Isento) {
                    $jurosMora->tipo = 0;
                } elseif ($this->juros->getTipo() === $this->juros::Diario) {
                    $jurosMora->tipo = 1;
                    $jurosMora->data = $this->juros->getData()->format('d.m.Y');
                    $jurosMora->valor = $this->juros->getValor();
                } elseif ($this->juros->getTipo() === $this->juros::Mensal) {
                    $jurosMora->tipo = 2;
                    $jurosMora->data = $this->juros->getData()->format('d.m.Y');
                    $jurosMora->porcentagem = $this->juros->getValor();
                } else {
                    throw new \InvalidArgumentException('Código do tipo de juros inválido.');
                }
                $boleto->jurosMora = $jurosMora;
            } else {
                $jurosMora = new \stdClass();
                $jurosMora->tipo = 0;
                $boleto->jurosMora = $jurosMora;
            }

            // MULTA
            if (!is_null($this->multa)) {
                $multa = new \stdClass();
                $multa->tipo = 2;
                $multa->porcentagem = $this->multa->getPercentual();
                $multa->data = $this->multa->getData()->format('d.m.Y');
                $boleto->multa = $multa;
            } else {
                $multa = new \stdClass();
                $multa->tipo = 0;
                $boleto->multa = $multa;
            }


            $pagador = new \stdClass();
            $pagador->tipoInscricao = $this->pagador->getTipoDocumento() === 'CPF' ? 1 : 2;
            $pagador->numeroInscricao = (int)$this->pagador->getDocumento();
            $pagador->nome = $this->pagador->getNome();
            $pagador->endereco = $this->pagador->getLogradouro() . ' ' . $this->pagador->getNumero();
            $pagador->cep = Helper::number($this->pagador->getCep());
            $pagador->cidade = $this->pagador->getCidade();
            $pagador->bairro = $this->pagador->getBairro();
            $pagador->uf = $this->pagador->getUf();
            $pagador->telefone = $this->pagador->getTelefone();

            $beneficiarioFinal = new \stdClass();
            $beneficiarioFinal->tipoInscricao = $this->beneficiario->getTipoDocumento() === 'F' ? 1 : 2;
            $beneficiarioFinal->numeroInscricao = (int)$this->beneficiario->getDocumento();
            $beneficiarioFinal->nome = $this->beneficiario->getNome();

            if ($this->isSandbox()) {
                $pagador->tipoInscricao = 2;
                $pagador->numeroInscricao = 74910037000193;
                $pagador->nome = 'TECIDOS FARIA DUARTE';

                $beneficiarioFinal = new \stdClass();
                $beneficiarioFinal->tipoInscricao = 1;
                $beneficiarioFinal->numeroInscricao = 66779051870;
                $beneficiarioFinal->nome = 'Dirceu Borboleta';
            }

            $boleto->pagador = $pagador;
            $boleto->beneficiarioFinal = $beneficiarioFinal;

            $boleto->indicadorPix = $this->pix ? 'S' : 'N';


            if ($this->isSandbox()) {
                $endpoint = 'https://api.hm.bb.com.br';
            } else {
                $endpoint = 'https://api.bb.com.br';
            }

            $endpoint .= '/cobrancas/v2/boletos?gw-dev-app-key=' . $this->getAppKey();


            $token = $this->getToken();

            $client = new Client(['verify' => false]);
            $res = $client->request('POST', $endpoint, [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'json' => $boleto
            ]);

            if ($res->getStatusCode() === 200 || $res->getStatusCode() === 201) {
                $body = json_decode($res->getBody()->getContents());
                $this->setCodigobarras($body->codigoBarraNumerico);
                $this->setLinhadigitavel($body->linhaDigitavel);
                $this->setPixQrCode($body->qrCode->emv);
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $err = json_decode($e->getResponse()->getBody()->getContents());
                if (isset($err->erros)) {
                    foreach ($err->erros as $error) {
                        throw new InvalidArgumentException($error->codigo, $error->mensagem, $e->getCode());
                    }
                } elseif (isset($err->error)) {
                    throw new InvalidArgumentException($error->statusCode ?? 500, $error->message, $e->getCode());
                } else {
                    throw new InvalidArgumentException(500, 'Erro desconhecido' . $e->getMessage(), $e->getCode());
                }
            } else {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    private function getToken()
    {
        try {

            $key = sha1('boleto-bb' . $this->convenio);

            if ($this->isSandbox()) {
                $endpoint = 'https://oauth.hm.bb.com.br/oauth/token';
            } else {
                $endpoint = 'https://oauth.bb.com.br/oauth/token';
            }

            $item = $this->cache->getItem($key);
            if (!$item->isHit()) {
                $client = new Client(['auth' => [$this->getClientId(), $this->getSecretId()], 'verify' => false]);
                $res = $client->request('POST', $endpoint, [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Cache-Control' => 'no-cache'
                    ],
                    'body' => 'grant_type=client_credentials&scope=' . $this->getScope()
                ]);

                if (in_array($res->getStatusCode(), [200, 201])) {
                    $json = $res->getBody()->getContents();
                    $arr = \GuzzleHttp\json_decode($json);

                    $item->set($arr->access_token);
                    $item->expiresAfter($arr->expires_in);
                    $this->cache->saveDeferred($item);
                    return $item->get();
                }

                return null;

            }
            return $item->get();

        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    public function baixar()
    {
        try {           
        
            if ($this->getClient() !== 'API') {
                throw new InvalidArgumentException('O método de cancelamento está disponivel apenas para o API do banco do Brasil.');
            }
    
            if ($this->isSandbox()) {
                $endpoint = 'https://api.hm.bb.com.br';
            } else {
                $endpoint = 'https://api.bb.com.br';
            }
    
            $id = "000{$this->getConvenio()}{$this->getNossoNumero()}";
    
    
            $endpoint .= "/cobrancas/v2/boletos/{$id}/baixar";
    
            $token = $this->getToken();
    
            $client = new Client(['verify' => false]);
            $res = $client->request('POST', $endpoint, [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'query' => ['gw-dev-app-key' => $this->getAppKey()],
                'json' => ['numeroConvenio' => $this->getConvenio()],
            ]);
    
            if ($res->getStatusCode() === 200 || $res->getStatusCode() === 201) {
                $body = $res->getBody()->getContents();
                $data = json_decode($body);
            }

        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $err = json_decode($e->getResponse()->getBody()->getContents());
                if (isset($err->erros)) {
                    foreach ($err->erros as $error) {
                        throw new InvalidArgumentException($error->codigo, $error->mensagem, $e->getCode());
                    }
                } elseif (isset($err->errors)) {
                    foreach ($err->errors as $error) {
                        throw new InvalidArgumentException($error->code, $error->message, $e->getCode());
                    }
                } elseif (isset($err->error)) {
                    throw new InvalidArgumentException($error->statusCode ?? 500, $error->message, $e->getCode());
                } else {
                    throw new InvalidArgumentException(500, 'Erro desconhecido' . $e->getMessage(), $e->getCode());
                }
            } else {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

    }

    /**
     * @return string
     */
    public function getClient(): string
    {
        return $this->client;
    }

    /**
     * @param string $client
     * @return BrasilService
     */
    public function setClient(string $client): BrasilService
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @return null
     */
    public function getAppKey()
    {
        return $this->appKey;
    }

    /**
     * @param string $appKey
     * @return BrasilService
     */
    public function setAppKey(string $appKey): BrasilService
    {
        $this->appKey = $appKey;
        return $this;
    }


    private function getScope(): string
    {
        if ($this->getClient() === 'API') {
            return 'cobrancas.boletos-requisicao cobrancas.boletos-info';
        } else {
            return 'cobranca.registro-boletos';
        }

    }

    
    /**
     * @param Datetime
     */
    public function getDataBaixa()
    {
        if (is_null($this->databaixa)) {
            throw new \InvalidArgumentException('Data Baixa inválido.');
        }
        return $this->databaixa;
    }
}

