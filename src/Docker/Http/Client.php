<?php

namespace Docker\Http;

use Docker\Http\Exception\ParseErrorException;

/**
 * Docker\Http\Client
 */
class Client
{
    const CHUNK_SIZE = 8192;

    /**
     * @var resource
     */
    private $socket;

    /**
     * @var Docker\Http\ResponseParser
     */
    private $parser;

    /**
     * @var string
     */
    private $userAgent = 'Docker-PHP';

    /**
     * @param string $spec
     */
    public function __construct($spec)
    {
        $this->spec = $spec;
        $this->parser = new ResponseParser();
    }

    /**
     * @return array
     */
    public function getDefaultHeaders()
    {
        return [
            'Host' => $this->spec,
            'User-Agent' => $this->userAgent,
        ];
    }

    /**
     * @param Docker\Http\Request $request
     *
     * @return Docker\Http\Response
     */
    public function send(Request $request)
    {
        $socket = stream_socket_client($this->spec);
        fwrite($socket, $request->getHeadersAsString());
        $content = $request->getContent();

        if (is_resource($content)) {
            while (false !== ($read = fread($content, self::CHUNK_SIZE))) {
                $frame = dechex(mb_strlen($read))."\r\n".$read."\r\n";
                fwrite($socket, $frame);

                if (empty($read)) {
                    break;
                }
            }

            fclose($content);
            fwrite($socket, "0\r\n\r\n");
        } else {
            fwrite($socket, $content);
        }

        stream_set_timeout($socket, $request->getTimeout());

        try {
            $response = $this->parser->parse($socket);
        } catch (ParseErrorException $e) {
            $e->setRequest($request);

            throw $e;
        }

        if (!$response instanceof StreamedResponse && $response->headers->get('Connection') === 'close') {
            fclose($socket);
        }

        return $response;
    }

    /**
     * @param string $uri
     *
     * @return Docker\Http\Request
     */
    public function get($uri, $headers = array())
    {
        $headers = array_replace($this->getDefaultHeaders(), $headers);

        return new Request('GET', $uri, $headers);
    }

    /**
     * @param string $uri
     *
     * @return Docker\Http\Request
     */
    public function post($uri, $headers = array())
    {
        $headers = array_replace($this->getDefaultHeaders(), $headers);

        return new Request('POST', $uri, $headers);
    }

    /**
     * @param string $uri
     *
     * @return Docker\Http\Request
     */
    public function delete($uri, $headers = array())
    {
        $headers = array_replace($this->getDefaultHeaders(), $headers);

        return new Request('DELETE', $uri, $headers);
    }
}