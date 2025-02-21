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
use Boleto\Service\CaixaSoapCliente;
use DateTime;
use Exception;
use SimpleXMLElement;


class CaixaService implements InterfaceBank
{
    private DateTime|null $vencimento, $emissao;
    private float|null $valor;
    private string|null $convenio;
    private string|null $nossonumero;
    private string $carteira;
    private string $codigobarras;
    private string $linhadigitavel;
    private int $prazodevolucao;
    private Pagador|null $pagador;
    private Beneficiario $beneficiario;
    private Juros $juros;
    private Multa $multa;

    /**
     * @var Desconto[]
     */
    private array $desconto = [];
    private bool $pix = false;
    private ?string $pixQrCode;

    /**
     * CaixaService constructor.
     * @param DateTime|null $vencimento
     * @param float|null $valor
     * @param string|null $nossonumero
     * @param string|null $convenio
     * @param Pagador|null $pagador
     */
    public function __construct(DateTime $vencimento = null, float $valor = null, string $nossonumero = null, string $convenio = null, Pagador $pagador = null)
    {
        $this->emissao = new DateTime();
        $this->vencimento = $vencimento;
        $this->valor = $valor;
        $this->nossonumero = $nossonumero;
        $this->convenio = $convenio;
        $this->pagador = $pagador;
        $this->prazodevolucao = 29;
    }

    /**
     * @param DateTime $date
     * @return CaixaService
     */
    public function setEmissao(DateTime $date): CaixaService
    {
        $this->emissao = $date;
        return $this;
    }

    /**
     * @param DateTime $date
     * @return CaixaService
     */
    public function setVencimento(DateTime $date): CaixaService
    {
        $this->vencimento = $date;
        return $this;
    }

    /**
     * @param double $valor
     * @return CaixaService
     */
    public function setValor(float $valor): CaixaService
    {
        $this->valor = $valor;
        return $this;
    }

    /**
     * @param string|int $nossonumero
     * @return CaixaService
     */
    public function setNossoNumero(string|int $nossonumero): CaixaService
    {
        $this->nossonumero = $nossonumero;
        return $this;
    }

    /**
     * @param int $convenio
     * @return CaixaService
     */
    public function setConvenio($convenio): CaixaService
    {
        $this->convenio = $convenio;
        return $this;
    }


    /**
     * @param Pagador|null $pagador
     * @return CaixaService
     */
    public function setPagador(Pagador $pagador = null): CaixaService
    {
        $this->pagador = $pagador;
        return $this;
    }

    /**
     * @param Beneficiario|null $beneficiario
     * @return CaixaService
     */
    public function setBeneficiario(Beneficiario $beneficiario = null): CaixaService
    {
        $this->beneficiario = $beneficiario;
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
     * @param DateTime
     */
    public function getEmissao(): DateTime
    {
        if (is_null($this->emissao)) {
            throw new \InvalidArgumentException('Data Emissäo inválido.');
        }
        return $this->emissao;
    }

    /**
     * @return DateTime
     */
    public function getVencimento(): DateTime
    {
        if (is_null($this->vencimento)) {
            throw new \InvalidArgumentException('Data Vencimento inválido.');
        }
        return $this->vencimento;
    }

    /**
     * @return int
     */
    public function getCarteira(): int
    {
        return $this->carteira;
    }

    /**
     * @return double
     */
    public function getValor(): float
    {
        if (is_null($this->valor)) {
            throw new \InvalidArgumentException('Valor inválido.');
        }
        return $this->valor;
    }

    /**
     * @return string
     */
    public function getNossoNumero(): string
    {
        if (is_null($this->nossonumero)) {
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
     * @return CaixaService
     */
    public function setJuros(Juros $juros): CaixaService
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
     * @return CaixaService
     */
    public function setMulta(Multa $multa): CaixaService
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
     * @return CaixaService
     */
    public function setDesconto(Desconto $desconto): CaixaService
    {
        $this->desconto[] = $desconto;
        return $this;
    }

    /**
     * @return int
     */
    public function getPrazoDevolucao(): int
    {
        return $this->prazodevolucao;
    }

    /**
     * @param mixed $prazodevolucao
     * @return CaixaService
     */
    public function setPrazoDevolucao(int $prazodevolucao): CaixaService
    {
        $this->prazodevolucao = $prazodevolucao;
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
     * @return CaixaService
     */
    public function setPixQrCode(?string $pixQrCode): CaixaService
    {
        $this->pixQrCode = $pixQrCode;
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
     * @return CaixaService
     */
    public function setGerarPix(bool $pix): CaixaService
    {
        $this->pix = $pix;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function send(): void
    {

        try {

            $client = new CaixaSoapCliente('https://barramento.caixa.gov.br/sibar/ManutencaoCobrancaBancaria/Boleto/Externo?wsdl');
            //$client->__setLocation('https://barramento.caixa.gov.br/sibar/ManutencaoCobrancaBancaria/Boleto/Externo');

            $now = new DateTime();

            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><SERVICO_ENTRADA/>');

            $header = $xml->addChild('HEADER');
            $header->addChild('VERSAO', '3.2');
            $header->addChild('AUTENTICACAO', $this->getHash());
            $header->addChild('USUARIO_SERVICO', 'SGCBS02P');
            $header->addChild('OPERACAO', 'INCLUI_BOLETO');
            $header->addChild('SISTEMA_ORIGEM', 'SIGCB');
            $header->addChild('DATA_HORA', $now->format('YmdHis'));

            $dados = $xml->addChild('DADOS');
            $incluir = $dados->addChild('INCLUI_BOLETO');
            $incluir->addChild('CODIGO_BENEFICIARIO', $this->getConvenio());

            $titulo = $incluir->addChild('TITULO');
            if($this->getGerarPix()) {
                $titulo->addChild('TIPO', 'HIBRIDO');
            }

            $titulo->addChild('NOSSO_NUMERO', '14' . Helper::padLeft($this->getNossoNumero(), 15));
            $titulo->addChild('NUMERO_DOCUMENTO', substr($this->getNossoNumero(), -11));
            $titulo->addChild('DATA_VENCIMENTO', $this->getVencimento()->format('Y-m-d'));
            $titulo->addChild('VALOR', $this->getValor());
            $titulo->addChild('TIPO_ESPECIE', 99);
            $titulo->addChild('FLAG_ACEITE', 'N');
            $titulo->addChild('DATA_EMISSAO', $this->getEmissao()->format('Y-m-d'));

            if (!empty($this->multa)) {
                $multa = $titulo->addChild('MULTA');
                $multa->addChild('DATA', $this->multa->getData()->format('Y-m-d'));
                $multa->addChild('PERCENTUAL', $this->multa->getPercentual());
            }


            if (count($this->desconto) > 0) {
                if (count($this->desconto) > 3) {
                    throw new \InvalidArgumentException('Quantidade desconto informado maior que 3.');
                }
                foreach ($this->desconto as $desconto) {
                    if ($desconto->getTipo() === $desconto::Valor) {
                        $desc = $titulo->addChild('DESCONTOS');
                        $desc->addChild('DATA', $desconto->getData()->format('Y-m-d'));
                        $desc->addChild('VALOR', $desconto->getValor());
                    } elseif ($desconto->getTipo() === $desconto::Percentual) {
                        $desc = $titulo->addChild('DESCONTOS');
                        $desc->addChild('DATA', $desconto->getData()->format('Y-m-d'));
                        $desc->addChild('PERCENTUAL', $desconto->getValor());
                    } else {
                        throw new \InvalidArgumentException('Código do tipo de desconto inválido.');
                    }
                }
            }

            $juros = $titulo->addChild('JUROS_MORA');
            if (!empty($this->juros)) {
                if ($this->juros->getTipo() === $this->juros::Diario) {
                    $juros->addChild('TIPO', 'VALOR_POR_DIA');
                    $juros->addChild('DATA', $this->juros->getData()->format('Y-m-d'));
                    $juros->addChild('VALOR', $this->juros->getValor());
                } elseif ($this->juros->getTipo() === $this->juros::Mensal) {
                    $juros->addChild('TIPO', 'TAXA_MENSAL');
                    $juros->addChild('DATA', $this->juros->getData()->format('Y-m-d'));
                    $juros->addChild('PERCENTUAL', $this->juros->getValor());
                } elseif ($this->juros->getTipo() === $this->juros::Isento) {
                    $juros->addChild('TIPO', 'ISENTO');
                    $juros->addChild('VALOR', 0);
                    $juros->addChild('PERCENTUAL', 0);
                } else {
                    throw new \InvalidArgumentException('Código do tipo de juros inválido.');
                }
            } else {
                $juros->addChild('TIPO', 'ISENTO');
                $juros->addChild('VALOR', 0);
                $juros->addChild('PERCENTUAL', 0);
            }

            $titulo->addChild('VALOR_ABATIMENTO', 0);

            $pos = $titulo->addChild('POS_VENCIMENTO');
            $pos->addChild('ACAO', 'DEVOLVER');
            $pos->addChild('NUMERO_DIAS', $this->getPrazoDevolucao());

            $titulo->addChild('CODIGO_MOEDA', '09');

            $pagador = $titulo->addChild('PAGADOR');
            if ($this->pagador->getTipoDocumento() === 'CPF') {
                $pagador->addChild('CPF', $this->pagador->getDocumento());
                $pagador->addChild('NOME', substr(str_replace("&", "", Helper::ascii($this->pagador->getNome())), 0, 40));
            } else {
                $pagador->addChild('CNPJ', $this->pagador->getDocumento());
                $pagador->addChild('RAZAO_SOCIAL', substr(str_replace("&", "", Helper::ascii($this->pagador->getNome())), 0, 40));
            }

            $endereco = $pagador->addChild('ENDERECO');

            if(!empty($this->pagador->getLogradouro())) {
                $endereco->addChild('LOGRADOURO', substr(str_replace("&", "", Helper::ascii($this->pagador->getLogradouro())) . ' ' . $this->pagador->getNumero(), 0, 40));
            } else {
                throw new InvalidArgumentException('X997', '(X997) LOGRADOURO DO PAGADOR INVALIDO');
            }

            if(!empty($this->pagador->getBairro())) {
                $endereco->addChild('BAIRRO', substr(str_replace("&", "", Helper::ascii($this->pagador->getBairro())), 0, 15));
            } else {
                throw new InvalidArgumentException('X999', '(X999) BAIRRO DO PAGADOR INVALIDO');
            }

            if(!empty($this->pagador->getCidade())) {
                $endereco->addChild('CIDADE', substr(str_replace("&", "", Helper::ascii($this->pagador->getCidade())), 0, 15));
            } else {
                throw new InvalidArgumentException('X998', '(X998) CIDADE DO PAGADOR INVALIDO');
            }

            if(!empty($this->pagador->getUf())) {
                $endereco->addChild('UF', Helper::ascii($this->pagador->getUf()));
            } else {
                throw new InvalidArgumentException('X996', '(X996) UF DO PAGADOR INVALIDO');
            }

            if(!empty($this->pagador->getCep())) {
                $endereco->addChild('CEP', Helper::number($this->pagador->getCep()));
            } else {
                throw new InvalidArgumentException('X995', '(X995) CEP DO PAGADOR INVALIDO');
            }

            $arr = json_decode(json_encode((array)$xml), 1);

            $descontos = &$arr["DADOS"]["INCLUI_BOLETO"]["TITULO"]["DESCONTOS"];
            if(isset($descontos["DATA"])){
                $descontos = [$descontos];
            }

            //file_put_contents('C:/home/tmp/' . $this->getNossoNumero() . '.txt', print_r($arr, true));

            $result = $client->__soapCall("INCLUI_BOLETO", [$arr]);

            if (!isset($result->DADOS->CONTROLE_NEGOCIAL)) {
                throw new InvalidArgumentException($result->COD_RETORNO, trim($result->RETORNO));
            }

            if ($result->DADOS->CONTROLE_NEGOCIAL->COD_RETORNO !== "0") {
                if (preg_match('/\((.*?)\)/i', $result->DADOS->CONTROLE_NEGOCIAL->MENSAGENS->RETORNO, $match)) {
                    $codigo = trim($match[1]);
                } else {
                    $checksum = crc32(trim($result->DADOS->CONTROLE_NEGOCIAL->MENSAGENS->RETORNO));
                    $codigo = sprintf("%u\n", $checksum);
                }
                throw new InvalidArgumentException($codigo, $result->DADOS->CONTROLE_NEGOCIAL->MENSAGENS->RETORNO);
            }

            $this->setCodigobarras($result->DADOS->INCLUI_BOLETO->CODIGO_BARRAS);
            $this->setLinhadigitavel($result->DADOS->INCLUI_BOLETO->LINHA_DIGITAVEL);
            $this->setPixQrCode($result->DADOS->INCLUI_BOLETO->QRCODE ?? null);

        } catch (\SoapFault $sf) {
            throw new Exception($sf->faultstring, 500);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), 500, $e);
        }

    }

    /**
     * @throws Exception
     */
    public function alterar(): void
    {

        try {

            $client = new CaixaSoapCliente('https://barramento.caixa.gov.br/sibar/ManutencaoCobrancaBancaria/Boleto/Externo?wsdl');
            $now = new DateTime();

            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><SERVICO_ENTRADA/>');

            $header = $xml->addChild('HEADER');
            $header->addChild('VERSAO', '3.2');
            $header->addChild('AUTENTICACAO', $this->getHash('ALTERA_BOLETO'));
            $header->addChild('USUARIO_SERVICO', 'SGCBS02P');
            $header->addChild('OPERACAO', 'ALTERA_BOLETO');
            $header->addChild('SISTEMA_ORIGEM', 'SIGCB');
            $header->addChild('DATA_HORA', $now->format('YmdHis'));

            $dados = $xml->addChild('DADOS');
            $alterar = $dados->addChild('ALTERA_BOLETO');
            $alterar->addChild('CODIGO_BENEFICIARIO', $this->getConvenio());

            $titulo = $alterar->addChild('TITULO');
            $titulo->addChild('NOSSO_NUMERO', '14' . Helper::padLeft($this->getNossoNumero(), 15));
            $titulo->addChild('NUMERO_DOCUMENTO', substr($this->getNossoNumero(), -11));
            $titulo->addChild('DATA_VENCIMENTO', $this->getVencimento()->format('Y-m-d'));
            $titulo->addChild('VALOR', $this->getValor());


            $arr = json_decode(json_encode((array)$xml), 1);

            //file_put_contents('C:/home/tmp/' . $this->getNossoNumero() . '.txt', print_r($arr, true));

            $result = $client->__soapCall("ALTERA_BOLETO", [$arr]);

            if (!isset($result->DADOS->CONTROLE_NEGOCIAL)) {
                throw new InvalidArgumentException($result->COD_RETORNO, trim($result->RETORNO));
            }

            if ($result->DADOS->CONTROLE_NEGOCIAL->COD_RETORNO !== "0") {
                if (preg_match('/\((.*?)\)/i', $result->DADOS->CONTROLE_NEGOCIAL->MENSAGENS->RETORNO, $match)) {
                    $codigo = trim($match[1]);
                } else {
                    $checksum = crc32(trim($result->DADOS->CONTROLE_NEGOCIAL->MENSAGENS->RETORNO));
                    $codigo = sprintf("%u\n", $checksum);
                }
                throw new InvalidArgumentException($codigo, $result->DADOS->CONTROLE_NEGOCIAL->MENSAGENS->RETORNO);
            }

            $this->setCodigobarras($result->DADOS->ALTERA_BOLETO->CODIGO_BARRAS);
            $this->setLinhadigitavel($result->DADOS->ALTERA_BOLETO->LINHA_DIGITAVEL);
            $this->setPixQrCode($result->DADOS->ALTERA_BOLETO->QRCODE ?? null);

        } catch (\SoapFault $sf) {
            throw new Exception($sf->faultstring, 500);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), 500, $e);
        }

    }

    /**
     * @throws Exception
     */
    public function baixar(): void
    {

        try {

            $client = new CaixaSoapCliente('https://barramento.caixa.gov.br/sibar/ManutencaoCobrancaBancaria/Boleto/Externo?wsdl');

            $now = new DateTime();

            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><SERVICO_ENTRADA/>');

            $header = $xml->addChild('HEADER');
            $header->addChild('VERSAO', '3.2');
            $header->addChild('AUTENTICACAO', $this->getHash('BAIXA_BOLETO'));
            $header->addChild('USUARIO_SERVICO', 'SGCBS02P');
            $header->addChild('OPERACAO', 'BAIXA_BOLETO');
            $header->addChild('SISTEMA_ORIGEM', 'SIGCB');
            $header->addChild('DATA_HORA', $now->format('YmdHis'));

            $dados = $xml->addChild('DADOS');
            $alterar = $dados->addChild('BAIXA_BOLETO');
            $alterar->addChild('CODIGO_BENEFICIARIO', $this->getConvenio());
            $alterar->addChild('NOSSO_NUMERO', '14' . Helper::padLeft($this->getNossoNumero(), 15));

            $arr = json_decode(json_encode((array)$xml), 1);

            //file_put_contents('C:/home/tmp/' . $this->getNossoNumero() . '.txt', print_r($arr, true));

            $result = $client->__soapCall("BAIXA_BOLETO", [$arr]);

            if (!isset($result->DADOS->CONTROLE_NEGOCIAL)) {
                throw new InvalidArgumentException($result->COD_RETORNO, trim($result->RETORNO));
            }

            if ($result->DADOS->CONTROLE_NEGOCIAL->COD_RETORNO !== "0") {
                if (preg_match('/\((.*?)\)/i', $result->DADOS->CONTROLE_NEGOCIAL->MENSAGENS->RETORNO, $match)) {
                    $codigo = trim($match[1]);
                } else {
                    $checksum = crc32(trim($result->DADOS->CONTROLE_NEGOCIAL->MENSAGENS->RETORNO));
                    $codigo = sprintf("%u\n", $checksum);
                }
                throw new InvalidArgumentException($codigo, $result->DADOS->CONTROLE_NEGOCIAL->MENSAGENS->RETORNO);
            }

        } catch (\SoapFault $sf) {
            throw new Exception($sf->faultstring, 500);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), 500, $e);
        }

    }

    /**
     * @throws Exception
     */
    private function getHash($operacao = 'INCLUIR_BOLETO'): string
    {
        try {
            if($operacao === 'BAIXA_BOLETO') {
                $strVencimento = '0';
                $strValor = 0;
            } else {
                $strVencimento = $this->getVencimento()->format('dmY');
                $strValor = Helper::number(Helper::numberFormat($this->getValor()));
            }

            $str = Helper::padLeft($this->getConvenio(), 7)
                . '14' . Helper::padLeft($this->getNossoNumero(), 15)
                . Helper::padLeft($strVencimento, 8)
                . Helper::padLeft($strValor, 15)
                . Helper::padLeft($this->beneficiario->getDocumento(), 14);

            return base64_encode(hash('sha256', $str, true));

        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }
}
