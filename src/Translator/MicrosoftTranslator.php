<?php declare(strict_types=1);

namespace Inhere\Comlib\Translator;

use Exception;
use Inhere\Comlib\HttpClient;
use ReflectionException;
use RuntimeException;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Bean\Exception\ContainerException;
use Swoft\Stdlib\Helper\ObjectHelper;
use function count;
use function json_decode;

/**
 * class MicrosoftTranslator
 *
 * @see https://github.com/MicrosoftTranslator/Text-Translation-API-V3-PHP/blob/master/Translate.php
 *
 * @Bean("msTranslator")
 */
class MicrosoftTranslator extends AbstractTranslator
{
    public const BASE_URL = 'https://api.cognitive.microsofttranslator.com/translate?api-version=3.0';

    /**
     * @see https://docs.microsoft.com/zh-cn/azure/cognitive-services/translator/reference/v3-0-translate
     * @var [type]
     */
    private const QUERY_PARAMS = [
        'from'     => 'zh-CN', // 'zh-Hans'
        'to'       => 'en',
        'textType' => 'plain', // plain(默认) html
        // 'category' => '',
    ];

    /**
     * @var string
     */
    private $key = '';

    /**
     * Translate the input text content.
     *
     * Will return like:
     *  [
     *     'text' => 'translated text'
     *     'to'   => 'en'
     *  ]
     *
     * If error, return:
     *  [
     *      'code' => 401000,
     *      'message' => 'error message'
     *  ]
     *
     * @param string $text The text will be translate
     * @param array  $params
     *
     * @return array
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function translate(string $text, array $params = []): array
    {
        $data = [
            ['Text' => $text],
        ];
        $ret  = $this->translateBatch($data, $params);

        // check error
        if (isset($ret['error'])) {
            return $ret['error'];
        }

        if (count($ret) > 0) {
            return $ret[0]['translations'][0] ?? [];
        }

        return [];
    }

    /**
     * @param array $data There are texts will be translate [
     *                    ['Text' => 'text ...'],
     *                    ['Text' => 'text ...'],
     *                    ]
     * @param array $params
     *
     * @return array
     * @throws ReflectionException
     * @throws ContainerException
     * @throws Exception
     */
    public function translateBatch(array $data, array $params = []): array
    {
        if (!$this->key) {
            throw new RuntimeException('must be set the key for use translate service');
        }

        $headers = [
            'Ocp-Apim-Subscription-Key' => $this->key,
            'Content-Type'              => 'application/x-www-form-urlencoded',
            // 'Content-Type'           => 'text/plain',
            'X-ClientTraceId'           => $this->genGuid(),
        ];

        $queryParams = $params ? array_merge(self::QUERY_PARAMS, $params) : self::QUERY_PARAMS;
        $queryString = $this->client->buildQuery($queryParams);

        $resp = $this->client->json(self::BASE_URL . '&' . $queryString, $data, [
            'headers' => $headers,
        ]);

        $result = $resp->getBody()->getContents();

        return (array)json_decode($result, true);
    }

    /**
     * @return string
     * @throws Exception
     */
    public function genGuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0x0fff) | 0x4000, random_int(0, 0x3fff) | 0x8000, random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $key
     */
    public function setKey(string $key): void
    {
        $this->key = $key;
    }
}
