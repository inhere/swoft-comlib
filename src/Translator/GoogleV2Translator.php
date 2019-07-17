<?php declare(strict_types=1);

namespace Inhere\Comlib\Translator;

use function array_merge;
use InvalidArgumentException;
use ReflectionException;
use RuntimeException;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Bean\Exception\ContainerException;
use function strlen;

/**
 * Class GoogleTranslator
 *
 * @link https://cloud.google.com/translate/docs/reference/rest/v2/translate
 * @Bean("gv2Translator")
 */
class GoogleV2Translator extends AbstractTranslator
{
    public const TRANS_API = 'https://translation.googleapis.com/language/translate/v2';
    // public const CN_BASH_URL = 'https://translate.google.cn/translate_a/single';

    /**
     * @link https://github.com/statickidz/php-google-translate-free
     */
    protected const POST_PARAMS = [
        'q'      => '',
        'key'    => '',
        'model'  => 'nmt', // nmt base
        'format' => 'html',
        'source' => 'zh-CN',
        'target' => 'en',
    ];

    /**
     * Api key
     *
     * @var string
     */
    private $key = '';

    /**
     * @param string $text
     * @param array  $params
     * - sl Source language
     * - tl Target language
     * - fmt Text format: html(default), text
     *
     * @return array
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function translate(string $text, array $params = []): array
    {
        if (strlen($text) >= 5000) {
            throw new InvalidArgumentException('Maximum number of characters exceeded: 5000');
        }

        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $params = array_merge(self::POST_PARAMS, $params);

        // Add text
        $params['q']   = $text;
        $params['key'] = $this->key;

        $resp = $this->client->post(self::TRANS_API, $params, [
            'headers' => $headers,
        ]);

        /* result:
         {
          "sentences": [
            {
              "trans": "Welcome",
              "orig": "欢迎你",
              "backend": 1
            },
            {
              "src_translit": "Huānyíng nǐ"
            }
          ],
          "src": "zh-CN",
          "confidence": 1,
          "ld_result": {
            "srclangs": [
              "zh-CN"
            ],
            "srclangs_confidences": [
              1
            ],
            "extended_srclangs": [
              "zh-CN"
            ]
          }
        }
         */
        $json = $resp->getBody()->getContents();

        return $this->getTranslatedText($json);
    }

    /**
     * Dump of the JSON's response in an array
     *
     * @param string $json The JSON object returned by the request function
     *
     * @return array
     * [
     *    'text' => '', A single string with the translation
     * ]
     */
    protected function getTranslatedText(string $json): array
    {
        $translated = '';
        $resultArray = (array)json_decode($json, true);

        if (!$resultArray) {
            $message = 'Google detected unusual traffic from your computer network, try again later (2 - 48 hours). raw: ' . $json;
            throw new RuntimeException($message);
        }

        // error
        if (isset($resultArray['error'])) {
            return [
                'code' => $resultArray['error']['code'],
                'message' => $resultArray['error']['message'],
            ];
        }

        if (!isset($resultArray['data']['translations'])) {
            return [
                'code' => 500,
                'message' => 'not found data. raw: ' . $json,
            ];
        }

        foreach ($resultArray['data']['translations'] as $s) {
            $translated .= $s['translatedText'] ?? '';
        }

        return [
            'text' => $translated,
        ];
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
