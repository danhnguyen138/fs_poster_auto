<?php

namespace FSPoster\App\Providers;

use Exception;
use FSP_GuzzleHttp\Client;
use FSPOSTER_Psr\Http\Message\ResponseInterface;

class GuzzleClient
{
    /** @var Client  */
    private $client;

    public function __construct(array $config = [])
    {
        $this->client = new Client($config);
    }

    /**
     * @throws Exception
     * @param string $method
     * @param string $url
     * @param array $options
     * @return ResponseInterface
     */
    public function request($method, $url, $options = [])
    {
        $method = strtolower($method);

        try
        {
            $response = $this->client->$method($url, $options);
        }
        catch (Exception $e)
        {
            if(!method_exists($e, 'getResponse') || empty($e->getResponse()))
            {
                throw $e;
            }

            $response = $e->getResponse();
        }

        return $response;
    }

    /**
     * @throws Exception
     * @param string $url
     * @param array $options
     * @return ResponseInterface
     */
    public function get($url, array $options = [])
    {
        return $this->request('get', $url, $options);
    }

    /**
     * @throws Exception
     * @param string $url
     * @param array $options
     * @return ResponseInterface
     */
    public function post($url, array $options = [])
    {
        return $this->request('post', $url, $options);
    }

    /**
     * @throws Exception
     * @param string $url
     * @param array $options
     * @return ResponseInterface
     */
    public function put($url, array $options = [])
    {
        return $this->request('put', $url, $options);
    }
}