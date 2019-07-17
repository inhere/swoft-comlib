<?php declare(strict_types=1);

namespace Inhere\Comlib\Translator;

use Inhere\Comlib\HttpClient;
use Swoft\Stdlib\Helper\ObjectHelper;

/**
 * Class AbstractTranslator
 *
 * @package Inhere\Comlib\Translator
 */
abstract class AbstractTranslator
{
    /**
     * @var HttpClient
     */
    protected $client;

    public function __construct(array $config = [])
    {
        ObjectHelper::init($this, $config);

        if (!$this->client) {
            $this->client = new HttpClient();
        }
    }

    /**
     * @return HttpClient
     */
    public function getClient(): HttpClient
    {
        return $this->client;
    }

    /**
     * @param HttpClient $client
     */
    public function setClient(HttpClient $client): void
    {
        $this->client = $client;
    }
}
