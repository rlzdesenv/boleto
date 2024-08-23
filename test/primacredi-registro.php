<?php
require __DIR__ . '/../vendor/autoload.php';

use Boleto\Bank\PrimaCrediService;
use Boleto\Entity\Beneficiario;
use Boleto\Entity\Pagador;
use Boleto\Entity\Juros;
use Boleto\Entity\Multa;
use Boleto\Entity\Desconto;

try {
    $pagador    = new Pagador('', '', '', '', null, '', '', '', '', '');
    $beneficiario    = new Beneficiario('', '', '', '', null, '', '', '', '', '');
    $vencimento = new DateTime('2024-08-23');

    // Multa e Juros precisa ser implementando só aceita valores expresso em reais
    $juros      = new Juros(Juros::Mensal, 1, new DateTime('2024-08-23'));
    $multa      = new Multa(2, new DateTime('2024-08-23'));

    $desconto1  = new Desconto(Desconto::Valor, 10.99, new DateTime('2024-08-27'));
    $desconto2  = new Desconto(Desconto::Valor, 5, new DateTime('2024-08-28'));
    $desconto3  = new Desconto(Desconto::Valor, 1.99, new DateTime('2024-08-29'));

    $primacredi = new PrimaCrediService();
    $primacredi->setDocumento('')
        ->setNossoNumero('')
        ->setVencimento($vencimento)
        ->setValor(120)
        ->setJuros($juros)
        ->setMulta($multa)
        ->setPagador($pagador)
        ->setBeneficiario($beneficiario)
        ->setAgencia('')
        ->setDesconto($desconto1)
        ->setDesconto($desconto2)
        ->setDesconto($desconto3)
        ->setToken('')
        ->setConvenio('')
        ->send();
    echo $primacredi->getLinhaDigitavel();
} catch (\Exception $e) {
    echo $e->getMessage();
}
