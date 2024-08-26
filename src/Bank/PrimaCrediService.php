<?php

namespace Boleto\Bank;

use Boleto\Entity\Beneficiario;
use Boleto\Entity\Desconto;
use Boleto\Entity\Juros;
use Boleto\Entity\Multa;
use Boleto\Entity\Pagador;
use Boleto\Exception\InvalidArgumentException;
use Cache\Adapter\Apcu\ApcuCachePool;
use DateTime;
use Exception;
use SimpleXMLElement;
use SoapClient;
use SoapFault;
use SoapHeader;
use const SOAP_COMPRESSION_ACCEPT;
use const SOAP_COMPRESSION_GZIP;

class PrimaCrediService extends AbstractBank implements InterfaceBank
{
    /**
     * @var Datetime
     */
    private $vencimento, $emissao, $databaixa;
    private $valor;
    private $convenio;
    private $nossonumero, $documento;
    private $codigobarras;
    private $linhadigitavel;
    private $prazodevolucao;
    private $agencia;

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

    private $token;
    private $cache;

    private $sandbox = false;

    /**
     * PrimaCredi constructor.
     * @param DateTime|null $vencimento
     * @param null $valor
     * @param null $nossonumero
     * @param null $convenio
     * @param Pagador|null $pagador
     * @param null $token
     * @throws Exception
     */
    public function __construct(Datetime $vencimento = null, $valor = null, $nossonumero = null, $convenio = null, Pagador $pagador = null, $token = null)
    {
        $this->cache = new ApcuCachePool();

        $this->emissao = new Datetime();
        $this->vencimento = $vencimento;
        $this->valor = $valor;
        $this->nossonumero = $nossonumero;
        $this->convenio = $convenio;
        $this->pagador = $pagador;
        $this->token = $token;
    }

    /**
     * @param Datetime $date
     * @return PrimaCrediService
     */
    public function setEmissao(Datetime $date): PrimaCrediService
    {
        $this->emissao = $date;
        return $this;
    }

    /**
     * @param Datetime $date
     * @return PrimaCrediService
     */
    public function setVencimento(Datetime $date): PrimaCrediService
    {
        $this->vencimento = $date;
        return $this;
    }

    /**
     * @param double $valor
     * @return PrimaCrediService
     */
    public function setValor($valor): PrimaCrediService
    {
        $this->valor = $valor;
        return $this;
    }

    /**
     * @param int $nossonumero
     * @return PrimaCrediService
     */
    public function setNossoNumero($nossonumero): PrimaCrediService
    {
        $this->nossonumero = $nossonumero;
        return $this;
    }

    /**
     * @param int $convenio
     * @return PrimaCrediService
     */
    public function setConvenio($convenio): PrimaCrediService
    {
        $this->convenio = $convenio;
        return $this;
    }

    /**
     * @param int $carteira
     * @return PrimaCrediService
     */
    public function setCarteira($carteira): PrimaCrediService
    {
        $this->carteira = $carteira;
        return $this;
    }

    /**
     * @param Pagador $pagador
     * @return PrimaCrediService
     */
    public function setPagador(Pagador $pagador = null): PrimaCrediService
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
     * @return PrimaCrediService
     */
    public function setBeneficiario(Beneficiario $beneficiario): PrimaCrediService
    {
        $this->beneficiario = $beneficiario;
        return $this;
    }

    /**
     * @param string $token
     * @return PrimaCrediService
     */
    public function setToken($token): PrimaCrediService
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return string
     */
    private function getToken()
    {
        if (is_null($this->token)) {
            throw new \InvalidArgumentException('O parâmetro token nulo.');
        }
        return $this->token;
    }


    /**
     * @param string $codigobarras
     * @return PrimaCrediService
     */
    private function setCodigobarras($codigobarras): PrimaCrediService
    {
        $this->codigobarras = $codigobarras;
        return $this;
    }

    /**
     * @param string $linhadigitavel
     * @return PrimaCrediService
     */
    private function setLinhadigitavel($linhadigitavel): PrimaCrediService
    {
        $this->linhadigitavel = $linhadigitavel;
        return $this;
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
     * @return string|null
     */
    public function getNossoNumero(): ?string
    {
        if (is_null($this->nossonumero)) {
            throw new \InvalidArgumentException('Nosso Numero inválido.');
        }

        if (strlen($this->nossonumero) <= 6) {
            return $this->getNossoNumeroCalculado();
        }

        return $this->nossonumero;
    }

    private function getNossoNumeroCalculado(): string
    {
        $digito = $this->modulo_11($this->getBeneficiario()->getDocumento());
        $agencia = str_pad($this->getAgencia(), 4, '0', STR_PAD_LEFT);
        $convenio = str_pad($this->getConvenio(), 6, '0', STR_PAD_LEFT);
        $nossonumero = str_pad(substr((string)$this->nossonumero, -6), 6, '0', STR_PAD_LEFT);
        return '097' . $digito . $agencia . $convenio . $nossonumero;
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
     * @return Juros
     */
    public function getJuros(): Juros
    {
        return $this->juros;
    }

    /**
     * @param Juros $juros
     * @return PrimaCrediService
     */
    public function setJuros(Juros $juros): PrimaCrediService
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
     * @return PrimaCrediService
     */
    public function setMulta(Multa $multa): PrimaCrediService
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
     * @return PrimaCrediService
     */
    public function setDesconto(Desconto $desconto): PrimaCrediService
    {
        array_push($this->desconto, $desconto);
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
     * @return PrimaCrediService
     */
    public function setSandbox(bool $sandbox): PrimaCrediService
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
     * @return PrimaCrediService
     */
    public function setPrazoDevolucao(int $prazodevolucao): PrimaCrediService
    {
        $this->prazodevolucao = $prazodevolucao;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDocumento()
    {
        return $this->documento;
    }

    /**
     * @param mixed $documento
     * @return PrimaCrediService
     */
    public function setDocumento($documento): PrimaCrediService
    {
        $this->documento = $documento;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPixQrCode(): ?string
    {
        return $this->pixqrcode;
    }

    public function setAgencia($agencia)
    {
        $this->agencia = $agencia;
        return $this;
    }

    private function getAgencia()
    {
        if (is_null($this->agencia)) {
            throw new \InvalidArgumentException('Agência inválida.');
        }
        return $this->agencia;
    }


    /**
     * @throws Exception
     */
    public function send()
    {
        try {

            if($this->getNossoNumeroCalculado() !== $this->getNossoNumero()) {
                throw new \InvalidArgumentException('Nosso Número inválido.');
            }

            $client = $this->getClient();

            $boleto = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><boleto/>');

            // $boleto = $titulo->addChild('boleto');

            $pagador = $boleto->addChild('pagador');
            $pagador->addChild('nome', $this->pagador->getNome());
            $pagador->addChild('nomeFantasia', '');
            $pagador->addChild('cpfCnpj', $this->pagador->getDocumento());

            $endereco = $pagador->addChild('endereco');
            $endereco->addChild('endereco', $this->pagador->getLogradouro());
            $endereco->addChild('numero', $this->pagador->getNumero());
            $endereco->addChild('complemento', $this->pagador->getComplemento() ?: '');
            $endereco->addChild('bairro', $this->pagador->getBairro());
            $endereco->addChild('cep', $this->pagador->getCep());
            $endereco->addChild('cidade', $this->pagador->getCidade());
            $endereco->addChild('uf', $this->pagador->getUf());


            $contatos = $pagador->addChild('contatos');
            if (!empty($this->pagador->getTelefone())) {
                $item = $contatos->addChild('item');
                $item->addChild('contato', $this->pagador->getTelefone());
                $item->addChild('tipoContato', 1);
            }
            if (!empty($this->pagador->getEmail())) {
                $item = $contatos->addChild('item');
                $item->addChild('contato', $this->pagador->getEmail());
                $item->addChild('tipoContato', 4);
            }

            $boleto->addChild('documento', $this->getDocumento() ?: $this->getNossoNumero());
            $boleto->addChild('nossonumero', $this->getNossoNumero());
            $boleto->addChild('dataEmissao', $this->getEmissao()->format('Y-m-d'));
            $boleto->addChild('dataVencimento', $this->getVencimento()->format('Y-m-d'));

            $dataDevolucao = clone $this->getVencimento();
            $dataDevolucao->modify("+{$this->getPrazoDevolucao()} days");
            $boleto->addChild('dataLimitePagamento', $dataDevolucao->format('Y-m-d'));

            $boleto->addChild('valor', $this->getValor());
            $boleto->addChild('quantidadeParcelas', 1);
            $boleto->addChild('intervaloParcela', 0);
            $boleto->addChild('codigoEspecie', '03');
            $protesto = $boleto->addChild('protesto');
            $protesto->addChild('dias', 0);
            $protesto->addChild('tipo', 3);


            if (count($this->desconto) > 0) {
                if (count($this->desconto) > 3) {
                    throw new \InvalidArgumentException('Quantidade desconto informado maior que 1.');
                }
                foreach ($this->desconto as $key => $value) {
                    if ($value->getTipo() === $value::Valor) {
                        $desconto = $boleto->addChild('desconto' . ($key + 1));
                        $desconto->addChild('tipo', '1');
                        $desconto->addChild('data', $value->getData()->format('Y-m-d'));
                        $desconto->addChild('valor', $value->getValor());
                    } elseif ($value->getTipo() === $value::Percentual) {
                        $desconto = $boleto->addChild('tipo', '2');
                        $desconto->addChild('data', $value->getData()->format('Y-m-d'));
                        $desconto->addChild('valor', $value->getValor());
                    } else {
                        throw new \InvalidArgumentException('Código do tipo de desconto inválido.');
                    }
                }
            }


            $multa = $this->multa;
            if (!is_null($this->multa)) {
                $m = $boleto->addChild('multa');
                $m->addChild('tipo', 2);
                $m->addChild('valor', $multa->getPercentual());

                $intervalo = $this->getVencimento()->diff($multa->getData());

                $carencia = $m->addChild('carencia');
                $carencia->addChild('tipo', 2);
                $carencia->addChild('dias', $intervalo->days);
            }

            $juros = $this->juros;
            if (!is_null($this->juros)) {
                if ($juros->getTipo() === $this->juros::Isento) {
                    $j = $boleto->addChild('juros');
                    $j->addChild('valor', 0);
                    $j->addChild('tipo', 3);
                } elseif ($juros->getTipo() === $this->juros::Diario) {
                    $j = $boleto->addChild('juros');
                    $j->addChild('valor', $juros->getValor());
                    $j->addChild('tipo', 1);

                    $intervalo = $this->getVencimento()->diff($juros->getData());

                    $carencia = $j->addChild('carencia');
                    $carencia->addChild('tipo', 2);
                    $carencia->addChild('dias', $intervalo->days);

                } elseif ($juros->getTipo() === $this->juros::Mensal) {
                    $j = $boleto->addChild('juros');
                    $j->addChild('valor', $juros->getValor());
                    $j->addChild('tipo', 2);

                    $intervalo = $this->getVencimento()->diff($juros->getData());

                    $carencia = $j->addChild('carencia');
                    $carencia->addChild('tipo', 2);
                    $carencia->addChild('dias', $intervalo->days);
                } else {
                    throw new \InvalidArgumentException('Código do tipo de juros inválido.');
                }
            }

            $boleto = json_decode(json_encode($boleto), true);
            $result = $client->__soapCall("gerarBoletos", ['layout' => 'default', 'boletos' => [$boleto]]);

            if (isset($result->erros)) {
                if (isset($result->erros->item->code)) {
                    throw new InvalidArgumentException($result->erros->item->code, $result->erros->item->message);
                }
                foreach ($result->erros->item as $error) {
                    throw new InvalidArgumentException($error->code, $error->message);
                }
            }

            $this->setCodigobarras($result->titulos->item->codigoBarras);
            $this->setLinhadigitavel($result->titulos->item->linhaDigitavel);

        } catch (SoapFault $sf) {
            throw new Exception($sf->faultstring, 500);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), 500, $e);
        }
    }


    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function baixar(): void
    {
        try {
            $boleto = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><boleto/>');
            $boleto->addChild('nossonumero', $this->getNossoNumero());

            $client = $this->getClient();
            $boleto = json_decode(json_encode($boleto), true);
            $result = $client->__soapCall("buscarBoleto", [$boleto]);

            if (isset($result->erros)) {
                if (isset($result->erros->item->code)) {
                    throw new InvalidArgumentException($result->erros->item->code, $result->erros->item->message);
                }
                foreach ($result->erros->item as $error) {
                    throw new InvalidArgumentException($error->code, $error->message);
                }
            }

            $boleto = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><boleto/>');
            $boleto->addChild('idWeb', $result->titulos->item->idWeb);
            $boleto->addChild('valor', $result->titulos->item->valor);
            $boleto->addChild('operacao', 'CANCELAMENTO');

            $result = $client->__soapCall("baixarBoleto", [$boleto]);

            if (isset($result->erros)) {
                if (isset($result->erros->item->code)) {
                    throw new InvalidArgumentException($result->erros->item->code, $result->erros->item->message);
                }
                foreach ($result->erros->item as $error) {
                    throw new InvalidArgumentException($error->code, $error->message);
                }
            }

        } catch (SoapFault $sf) {
            throw new Exception($sf->faultstring, 500);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), 500, $e);
        }

    }


    /**
     * @return DateTime
     */
    public function getDataBaixa(): DateTime
    {
        if (is_null($this->databaixa)) {
            throw new \InvalidArgumentException('Data Baixa inválido.');
        }
        return $this->databaixa;
    }

    /**
     * @throws SoapFault
     */
    private function getClient(): SoapClient
    {
        $endpoint = 'https://credisiscobranca.com.br/v2/ws?wsdl';

        $header = new SoapHeader(
            'urn:CredisisBoletoInterface',
            'Chave',
            [
                'token' => $this->getToken(),
                'convenio' => $this->getConvenio()
            ]
        );

        $client = new SoapClient($endpoint,
            [
                'trace' => TRUE,
                'exceptions' => TRUE,
                'encoding' => 'UTF-8',
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 30,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ])
            ]
        );
        $client->__setSoapHeaders($header);

        return $client;
    }
}
