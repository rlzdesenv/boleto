<?php
require __DIR__ . '/../vendor/autoload.php';

use Boleto\Bank\PrimaCrediService;

try {

    $primacredi = new PrimaCrediService();
    $primacredi->setNossoNumero('')
        ->setToken('')
        ->setConvenio('')
        ->baixar();
} catch (\Exception $e) {
    echo $e->getMessage();
}
