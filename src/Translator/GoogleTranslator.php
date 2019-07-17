<?php declare(strict_types=1);

namespace Inhere\Comlib\Translator;

use function array_merge;
use InvalidArgumentException;
use ReflectionException;
use RuntimeException;
use function strlen;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Bean\Exception\ContainerException;

/**
 * Class GoogleTranslator
 *
 * @Bean("ggTranslator")
 */
class GoogleTranslator extends AbstractTranslator
{
    public const EN_BASH_URL = 'https://translate.google.com/translate_a/single';
    public const CN_BASH_URL = 'https://translate.google.cn/translate_a/single';

    /**
     * @var array URL Parameters
     * @link https://github.com/inhere/google-translate-php/blob/master/src/GoogleTranslate.php
     */
    protected const URL_PARAMS = [
        'client'   => 't',
        'hl'       => 'en',
        'dt'       => [
            // Translate
            't',
            // Full translate with synonym ($bodyArray[1])
            'bd',
            // Other translate ($bodyArray[5] - in google translate page this shows when click on translated word)
            'at',
            // Example part ($bodyArray[13])
            'ex',
            // I don't know ($bodyArray[8])
            'ld',
            // Definition part with example ($bodyArray[12])
            'md',
            // I don't know ($bodyArray[8])
            'qca',
            // Read also part ($bodyArray[14])
            'rw',
            // I don't know
            'rm',
            // Full synonym ($bodyArray[11])
            'ss'
        ],
        'sl'       => null, // Source language
        'tl'       => null, // Target language
        'q'        => null, // String to translate
        'ie'       => 'UTF-8', // Input encoding
        'oe'       => 'UTF-8', // Output encoding
        'multires' => 1,
        'otf'      => 0,
        'pc'       => 1,
        'trs'      => 1,
        'ssel'     => 0,
        'tsel'     => 0,
        'kc'       => 1,
        'tk'       => null,
    ];

    /**
     * @link https://github.com/statickidz/php-google-translate-free
     */
    protected const QUERY_PARAMS = [
        'client' => 'at',
        'dj' => '1',
        'hl' => '', // should same as 'tl'
        'ie' => 'UTF-8',
        'oe' => 'UTF-8',
        // 'inputm' => '2',
        'otf' => '2',
        // 'iid' => 'ere',
        'key' => '',
    ];

    /**
     * Api key
     *
     * @var string
     */
    private $key = '';

    /**
     * @var string cn|en
     */
    private $baseUrlType = 'cn';

    /**
     * @param string $text
     * @param array  $params
     * - sl Source language
     * - tl Target language
     *
     * @return array
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function translate(string $text, array $params = []): array
    {
        if(strlen($text) >= 5000) {
            throw new InvalidArgumentException('Maximum number of characters exceeded: 5000');
        }

        $baseUrl = $this->getBaseUrl();
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $params['q'] = $text;
        $queryParams = array_merge(self::QUERY_PARAMS, [
            'key' => $this->key,
            'hl'  => $params['tl'],
        ]);
        $queryString = '&dt=at&dt=bd&dt=ex&dt=ld&dt=md&dt=qca&dt=rw&dt=rm&dt=ss&dt=t&';
        $queryString .= $this->client->buildQuery($queryParams);

        $resp = $this->client->post($baseUrl . $queryString, $params, [
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

        return $this->getSentencesFromJSON($json);
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
    protected function getSentencesFromJSON($json): array
    {
        $resultArray = (array)json_decode($json, true);
        $sentences = '';

        if(!$resultArray) {
            $message = 'Google detected unusual traffic from your computer network, try again later (2 - 48 hours)';
            throw new RuntimeException($message);
        }

        foreach ($resultArray['sentences'] as $s) {
            $sentences .= $s['trans'] ?? '';
        }

        return [
            'src'  => $resultArray['src'] ?? '',
            'text' => $sentences,
        ];
    }

    /**
     * @return string
     */
    protected function getBaseUrl(): string
    {
        if ($this->baseUrlType === 'cn') {
            return self::CN_BASH_URL;
        }

        return self::EN_BASH_URL;
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

    /**
     * @return string
     */
    public function getBaseUrlType(): string
    {
        return $this->baseUrlType;
    }

    /**
     * @param string $baseUrlType
     */
    public function setBaseUrlType(string $baseUrlType): void
    {
        $this->baseUrlType = $baseUrlType;
    }

}
