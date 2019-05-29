<?php declare(strict_types=1);

namespace Inhere\Comlib;

use ReflectionException;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Bean\Exception\ContainerException;
use Swoft\Console\Console;
use Swoft\Http\Message\ContentType;
use Swoft\Http\Message\Response;
use Swoole\Coroutine\Http\Client;
use function array_merge;
use function http_build_query;
use function in_array;
use function is_array;
use function json_encode;
use function parse_url;
use function strpos;
use function strtoupper;

/**
 * Class HttpClient
 *
 * @Bean()
 */
class HttpClient
{
    private const DEFAULT_URL_DATA = [
        'scheme'   => 'http',
        'host'     => 'localhost',
        'port'     => '80',
        'user'     => '',
        'pass'     => '',
        'path'     => '/',
        'query'    => '',
        'fragment' => '',
    ];

    /**
     * Send GET request
     *
     * @param string $url
     * @param array  $options
     *
     * @return Response
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function get(string $url, array $options = []): Response
    {
        return $this->request('GET', $url, $options);
    }

    /**
     * Send POST request
     *
     * @param string $url
     * @param array  $data
     * @param array  $options
     *
     * @return Response
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function post(string $url, array $data, array $options = []): Response
    {
        $options['data'] = $data;

        return $this->request('POST', $url, $options);
    }

    /**
     * Send JSON request
     *
     * @param string $url
     * @param array  $data
     * @param array  $options
     *
     * @return Response
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function json(string $url, array $data, array $options = []): Response
    {
        $options['json'] = $data;

        return $this->request('POST', $url, $options);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array  $options
     *
     * @return Response
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function request(string $method, string $url, array $options = []): Response
    {
        if (!isset($options['method'])) {
            $options['method'] = $method;
        }

        $info   = $this->parseUrl($url);
        $port   = (int)$info['port'];
        $client = new Client($info['host'], $port, $port === 443);

        // config request
        $uriPath = $this->configRequest($client, $info, $options);

        $client->execute($uriPath);

        Console::log("request $url, data:", [
            'errCode' => $client->errCode,
            'errMsg'  => $client->errMsg,
            'status'  => $client->statusCode,
        ]);

        // trans to psr7 response
        $resp = new Response();
        $resp = $resp->withContent($client->body)->withStatus($client->statusCode);

        // close connection
        $client->close();

        return $resp;
    }

    /**
     * @param Client $client
     * @param array  $info
     * @param array  $options
     *
     * @return string
     */
    private function configRequest(Client $client, array $info, array $options): string
    {
        $uriPath  = $info['path'];
        $method   = $options['method'] ?: 'GET';
        $headers  = $options['headers'] ?? [];
        $sendData = $options['data'] ?? [];

        // set request method
        $client->setMethod($method = strtoupper($method));

        // allow send data(POST, PUT, PATCH)
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $postData = '';
            if ($jsonMap = $options['json'] ?? []) {
                $postData = json_encode($jsonMap);

                // add content type
                $headers['Content-Type'] = ContentType::JSON;
            } elseif ($formMap = $options['form'] ?? []) {
                $postData = http_build_query($formMap);

                // add content type
                $headers['Content-Type'] = ContentType::FORM;
            } elseif ($sendData) {
                if (is_array($sendData)) {
                    $postData = http_build_query($sendData);

                    // add content type
                    $headers['Content-Type'] = ContentType::FORM;
                } else {
                    $postData = (string)$sendData;
                }
            }

            // has post data
            if ($postData) {
                $client->setData($postData);
            }
        } elseif ($queryMap = $options['query'] ?? $sendData) {
            $queryString = http_build_query($queryMap);

            // check sep check
            $sepChar = strpos($uriPath, '?') > 0 ? '&' : '?';
            $uriPath = $uriPath . $sepChar . $queryString;
        }

        /*
         * [key => value]
         */
        if ($headers) {
            $client->setHeaders($headers);
        }

        /*
         * [key => value]
         */
        if ($cookies = $options['cookies'] ?? []) {
            $client->setCookies($cookies);
        }

        /**
         * [
         *  timeout => 3 // 3 seconds
         *  keep_alive => true
         *  websocket_mask => true
         * ]
         *
         * more @see https://wiki.swoole.com/wiki/page/p-client_setting.html
         */
        if ($settings = $options['settings'] ?? []) {
            $client->set($settings);
        }

        return $uriPath;
    }

    /**
     * @param string $url
     *
     * @return array
     */
    private function parseUrl(string $url): array
    {
        $info = array_merge(self::DEFAULT_URL_DATA, parse_url($url));

        if ($info['scheme'] === 'https') {
            $info['port'] = 443;
        }

        return $info;
    }
}
