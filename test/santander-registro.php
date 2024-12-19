<?php
require __DIR__ . '/../vendor/autoload.php';


use Boleto\Bank\BradescoService;
use Boleto\Bank\SantanderService;
use Boleto\Entity\Beneficiario;
use Boleto\Entity\Certificado;
use Boleto\Entity\Desconto;
use Boleto\Entity\Juros;
use Boleto\Entity\Multa;
use Boleto\Entity\Pagador;
use GuzzleHttp\Exception\GuzzleException;

try {

    $certificado = new Certificado('', '');
    $beneficiario = new Beneficiario('Fulano da Silva', '20201210000155', 'Rua Antenor Guirlanda', '15', null, 'Casa Verde', 'São Paulo', 'SP', '02514-010');
    $pagador = new Pagador('Fulano da Silva', '62344900187', 'Rua Antenor Guirlanda', '15', null, 'Casa Verde', 'São Paulo', 'SP', '02514-010');

    $vencimento = new DateTime('2024-12-23');

    $juros = new Juros(Juros::Mensal, 2, new DateTime('2024-12-23'));
    $multa = new Multa(2, new DateTime('2024-12-23'));

    $desconto1 = new Desconto(1, 3, new DateTime('2024-12-19'));
    $desconto2 = new Desconto(1, 2, new DateTime('2024-12-20'));
    $desconto3 = new Desconto(1, 1, new DateTime('2024-12-23'));

    $santander = new SantanderService();
    $santander->setEmissao((new DateTime()))
        ->setVencimento($vencimento)
        ->setValor(5.5)
        ->setNossoNumero(856704)
        ->setConvenio('3568253')
        ->setPagador($pagador)
        ->setBeneficiario($beneficiario)
        ->setCertificado($certificado)
        ->setJuros($juros)
        ->setMulta($multa)
        ->setDesconto($desconto1)
        ->setDesconto($desconto2)
        ->setDesconto($desconto3)
        ->setSandbox(true)
        ->setClientId('')
        ->setSecretId('')
        ->setWorkspaceId('')
        ->setChavePix('20201210000155')
        ->setGerarPix(true)
        ->send();

    echo PHP_EOL . PHP_EOL . $santander->getLinhaDigitavel() . PHP_EOL . $santander->getCodigoBarras() .PHP_EOL . $santander->getPixQrCode() . PHP_EOL . PHP_EOL;

} catch (\Exception|GuzzleException $e) {
    echo $e->getMessage();
}
