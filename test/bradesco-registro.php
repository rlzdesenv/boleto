<?php
require __DIR__ . '/../vendor/autoload.php';


use Boleto\Bank\BradescoService;
use Boleto\Entity\Beneficiario;
use Boleto\Entity\Certificado;
use Boleto\Entity\Desconto;
use Boleto\Entity\Juros;
use Boleto\Entity\Multa;
use Boleto\Entity\Pagador;

try {

    $certificado = new Certificado('certificado.homologacao.pfx', '123456');
    $beneficiario = new Beneficiario('Fulano da Silva', '68542653101838', 'Rua Antenor Guirlanda', '15', null, 'Casa Verde', 'São Paulo', 'SP', '02514-010');
    $pagador = new Pagador('Fulano da Silva', '62344900187', 'Rua Antenor Guirlanda', '15', null, 'Casa Verde', 'São Paulo', 'SP', '02514-010');

    $vencimento = new DateTime('2024-11-30');

    $juros = new Juros(Juros::Mensal, 2, new DateTime('2024-11-30'));
    $multa = new Multa(2, new DateTime('2024-11-30'));

    $desconto1 = new Desconto(1, 3, new DateTime('2024-11-27'));
    $desconto2 = new Desconto(1, 2, new DateTime('2024-11-28'));
    $desconto3 = new Desconto(1, 1, new DateTime('2024-11-29'));

    $bradesco = new BradescoService();
    $bradesco->setEmissao((new DateTime()))
        ->setVencimento($vencimento)
        ->setValor(100)
        ->setNossoNumero(80000000023)
        ->setAgencia('3861')
        ->setConta('41000')
        ->setPagador($pagador)
        ->setBeneficiario($beneficiario)
        ->setCertificado($certificado)
        ->setJuros($juros)
        ->setMulta($multa)
        ->setDesconto($desconto1)
        ->setDesconto($desconto2)
        ->setDesconto($desconto3)
        ->setSandbox(true)
        ->setClientId('c79a7632-3549-4580-93c1-46a678b45103')
        ->send();

    echo $bradesco->getLinhaDigitavel() . PHP_EOL . $bradesco->getCodigoBarras();

} catch (\Exception $e) {
    echo $e->getMessage();
}
