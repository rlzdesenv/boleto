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
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
class BrasilService implements InterfaceBank
{

    private DateTime $emissao;
    private DateTime $vencimento;
    private float $valor;
    private string $convenio;
    private string $variacaocarteira;
    private string $nossonumero;
    private string $carteira;
    private string $codigobarras;
    private string $linhadigitavel;
    private ?string $pixQrCode;
    private int $prazodevolucao;
    private bool $pix = true;
    private Pagador $pagador;
    private Beneficiario $beneficiario;
    private ?Juros $juros = null;
    private ?Multa $multa = null;

    /**
     * @var Desconto[]
     */
    private array $desconto = [];

    private string $clientId;
    private string $secretId;
    private ApcuCachePool $cache;
    private bool $sandbox = false;
    private string $client = 'WEBSERVICE';
    private $appKey = null;


    /**
     * BrasilService constructor.
     * @param DateTime|null $vencimento
     * @param float|null $valor
     * @param null $nossonumero
     * @param null $carteira
     * @param null $convenio
     * @param null $variacaocarteira
     * @param Pagador|null $pagador
     * @param null $clientId
     * @param null $secretId
     */
    public function __construct(Datetime $vencimento = null, float $valor = null, $nossonumero = null, $carteira = null, $convenio = null, $variacaocarteira = null, Pagador $pagador = null, $clientId = null, $secretId = null)
    {
        $this->cache = new ApcuCachePool();

        $this->emissao = new Datetime();
        if (!empty($vencimento)) {
            $this->vencimento = $vencimento;
        }
        if (!empty($valor)) {
            $this->valor = $valor;
        }
        if (!empty($nossonumero)) {
            $this->nossonumero = $nossonumero;
        }
        if (!empty($carteira)) {
            $this->carteira = $carteira;
        }
        if (!empty($convenio)) {
            $this->convenio = $convenio;
        }
        if (!empty($variacaocarteira)) {
            $this->variacaocarteira = $variacaocarteira;
        }
        if (!empty($pagador)) {
            $this->pagador = $pagador;
        }
        if (!empty($clientId)) {
            $this->clientId = $clientId;
        }
        if (!empty($secretId)) {
            $this->secretId = $secretId;
        }
    }

    /**
     * @param Datetime $date
     * @return BrasilService
     */
    public function setEmissao(Datetime $date): BrasilService
    {
        $this->emissao = $date;
        return $this;
    }

    /**
     * @param Datetime $date
     * @return BrasilService
     */
    public function setVencimento(Datetime $date): BrasilService
    {
        $this->vencimento = $date;
        return $this;
    }

    /**
     * @param double $valor
     * @return BrasilService
     */
    public function setValor(float $valor): BrasilService
    {
        $this->valor = $valor;
        return $this;
    }

    /**
     * @param string $nossonumero
     * @return BrasilService
     */
    public function setNossoNumero(string $nossonumero): BrasilService
    {
        $this->nossonumero = $nossonumero;
        return $this;
    }

    /**
     * @param string $convenio
     * @return BrasilService
     */
    public function setConvenio(string $convenio): BrasilService
    {
        $this->convenio = $convenio;
        return $this;
    }

    /**
     * @param string $variacaocarteira
     * @return BrasilService
     */
    public function setVariacaoCarteira(string $variacaocarteira): BrasilService
    {
        $this->variacaocarteira = $variacaocarteira;
        return $this;
    }

    /**
     * @param string $carteira
     * @return BrasilService
     */
    public function setCarteira(string $carteira): BrasilService
    {
        $this->carteira = $carteira;
        return $this;
    }

    /**
     * @param Pagador $pagador
     * @return BrasilService
     */
    public function setPagador(Pagador $pagador): BrasilService
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
    public function setClientId(string $clientId): BrasilService
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * @param string $secretId
     * @return BrasilService
     */
    public function setSecretId(string $secretId): BrasilService
    {
        $this->secretId = $secretId;
        return $this;
    }

    /**
     * @return string
     */
    private function getClientId(): string
    {
        if (empty($this->clientId)) {
            throw new \InvalidArgumentException('O parâmetro clientId nulo.');
        }
        return $this->clientId;
    }

    /**
     * @return string
     */
    private function getSecretId(): string
    {
        if (empty($this->clientId)) {
            throw new \InvalidArgumentException('O parâmetro secretId nulo.');
        }
        return $this->secretId;
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
     * @return void
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
            throw new \InvalidArgumentException('Data Emissão inválido.');
        }
        return $this->emissao;
    }

    /**
     * @return DateTime
     */
    public function getVencimento(): DateTime
    {
        if (empty($this->vencimento)) {
            throw new \InvalidArgumentException('Data Vencimento inválido.');
        }
        return $this->vencimento;
    }

    /**
     * @return string
     */
    public function getCarteira(): string
    {
        if (empty($this->carteira)) {
            throw new \InvalidArgumentException('Carteira inválido.');
        }
        return $this->carteira;
    }

    /**
     * @return float
     */
    public function getValor(): float
    {
        if (empty($this->valor)) {
            throw new \InvalidArgumentException('Valor inválido.');
        }
        return $this->valor;
    }

    /**
     * @return string
     */
    public function getNossoNumero(): string
    {
        if (empty($this->nossonumero)) {
            throw new \InvalidArgumentException('Nosso Numero inválido.');
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
     * @return string
     */
    private function getConvenio(): string
    {
        if (empty($this->convenio)) {
            throw new \InvalidArgumentException('Convênio inválido.');
        }
        return $this->convenio;
    }

    /**
     * @return string
     */
    private function getVariacaoCarteira(): string
    {
        if (empty($this->variacaocarteira)) {
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
    public function getDesconto(): array
    {
        return $this->desconto;
    }

    /**
     * @param Desconto $desconto
     * @return BrasilService
     */
    public function setDesconto(Desconto $desconto): BrasilService
    {
        $this->desconto[] = $desconto;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPixQrCode(): ?string
    {
        return $this->pixQrCode;
    }

    /**
     * @param string|null $pixQrCode
     * @return BrasilService
     */
    public function setPixQrCode(?string $pixQrCode): BrasilService
    {
        $this->pixQrCode = $pixQrCode;
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
    public function getPrazoDevolucao(): int
    {
        return $this->prazodevolucao ?: 0;
    }

    /**
     * @param mixed $prazodevolucao
     * @return BrasilService
     */
    public function setPrazoDevolucao(int $prazodevolucao): BrasilService
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
     * @param boolean $pix
     * @return BrasilService
     */
    public function setGerarPix(bool $pix): BrasilService
    {
        $this->pix = $pix;
        return $this;
    }


    /**
     * @throws InvalidArgumentException
     * @throws GuzzleException
     * @throws Exception
     */
    private function send(): void
    {
        try {
            $boleto = new \stdClass();
            $boleto->numeroConvenio = $this->getConvenio();
            $boleto->numeroCarteira = $this->getCarteira();
            $boleto->numeroVariacaoCarteira = $this->getVariacaoCarteira();
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
            $multa = $this->multa;
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

    /**
     * @throws InvalidArgumentException
     * @throws GuzzleException
     * @throws Exception
     */
    public function baixar(): void
    {
        try {

            if ($this->getClient() !== 'API') {
                throw new Exception('O método de cancelamento está disponível apenas para o API do banco do Brasil.');
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


}

