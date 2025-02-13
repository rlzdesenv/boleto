<?php

namespace Boleto\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;

class ClientFactory
{
    private static ?Client $client = null;

    public static function getClient($endpoint): Client
    {
        if (self::$client === null) {
            $handlerStack = HandlerStack::create(new CurlHandler());
            self::$client = new Client([
                'base_uri' => $endpoint,
                'verify' => false,
                'handler' => $handlerStack,
                'timeout' => 10,
                'connect_timeout' => 5,
                'http_errors' => true,
                'headers' => [
                    'Connection' => 'keep-alive',
                ],
            ]);
        }

        return self::$client;
    }
}