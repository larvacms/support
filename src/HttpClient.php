<?php
/**
 * @copyright Copyright (c) 2018 Larva Information Technology Co., Ltd.
 * @link http://www.larvacent.com/
 * @license http://www.larvacent.com/license/
 */

namespace LarvaCMS\Support;

use Psr\Http\Message\ResponseInterface;

/**
 * Http 客户端
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class HttpClient extends BaseObject
{
    use Traits\HasHttpRequest;

    /**
     * @var string
     */
    protected $baseUri = '';

    /**
     * 获取基础路径
     * @return string
     */
    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    /**
     * 设置基础路径
     * @param string $baseUri
     * @return HttpClient
     */
    public function setBaseUri(string $baseUri): HttpClient
    {
        $this->baseUri = $baseUri;
        return $this;
    }

    /**
     * Make a get request.
     *
     * @param string $endpoint
     * @param array $query
     * @param array $headers
     * @return HttpResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get(string $endpoint, $query = [], $headers = []): HttpResponse
    {
        return $this->request('get', $endpoint, [
            'headers' => $headers,
            'query' => $query,
        ]);
    }

    /**
     * 获取JSON
     * @param string $endpoint
     * @param array $query
     * @param array $headers
     * @return HttpResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getJSON(string $endpoint, $query = [], $headers = []): HttpResponse
    {
        $headers['Accept'] = 'application/json';
        return $this->get($endpoint, $query, $headers);
    }

    /**
     * Make a post request.
     *
     * @param string $endpoint
     * @param string|array $params
     * @param array $headers
     * @return HttpResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function post(string $endpoint, $params, $headers = []): HttpResponse
    {
        $options = ['headers' => $headers];
        if (!is_array($params)) {
            $options['body'] = $params;
        } else {
            $options['form_params'] = $params;
        }
        return $this->request('post', $endpoint, $options);
    }

    /**
     * make a post xml request
     * @param string $endpoint
     * @param mixed $data
     * @param array $headers
     * @return HttpResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function postXML(string $endpoint, $data, $headers = []): HttpResponse
    {
        if ($data instanceof \DOMDocument) {
            $xml = $data->saveXML();
        } elseif ($data instanceof \SimpleXMLElement) {
            $xml = $data->saveXML();
        } else {
            $xml = $this->convertArrayToXml($data);
        }
        $header['Content-Type'] = 'application/xml; charset=UTF-8';
        return $this->post($endpoint, $xml, $headers);
    }

    /**
     * Make a post request.
     *
     * @param string $endpoint
     * @param array $params
     * @param array $headers
     * @return HttpResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function postJSON(string $endpoint, $params = [], $headers = []): HttpResponse
    {
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        return $this->post($endpoint, Json::encode($params), $headers);
    }

    /**
     * Make a put request.
     *
     * @param string $endpoint
     * @param $params
     * @param array $headers
     * @return HttpResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function put(string $endpoint, $params, $headers = []): HttpResponse
    {
        $options = ['headers' => $headers];
        if (!is_array($params)) {
            $options['body'] = $params;
        } else {
            $options['form_params'] = $params;
        }
        return $this->request('put', $endpoint, $options);
    }

    /**
     * Make a put request.
     *
     * @param string $endpoint
     * @param array $params
     * @param array $headers
     * @return HttpResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function putJSON(string $endpoint, $params = [], $headers = []): HttpResponse
    {
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        return $this->put($endpoint, Json::encode($params), $headers);
    }

    /**
     * Make a http request.
     *
     * @param string $method
     * @param string $url
     * @param array $options http://docs.guzzlephp.org/en/latest/request-options.html
     * @return HttpResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request(string $method, string $url, $options = []): HttpResponse
    {
        $response = $this->sendRequest($method, $url, $options);
        return new HttpResponse($response);
    }

    /**
     * Converts array to XML document.
     * @param array $arr
     * @return string
     */
    protected function convertArrayToXml(array $arr): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $root = new \DOMElement('xml');
        $dom->appendChild($root);
        $this->buildXml($root, $arr);
        return $dom->saveXML();
    }

    /**
     * Build xml
     * @param \DOMElement|string|array|object $element
     * @param mixed $data
     */
    protected function buildXml($element, $data)
    {
        if (is_array($data)) {
            foreach ($data as $name => $value) {
                if (is_int($name) && is_object($value)) {
                    $this->buildXml($element, $value);
                } elseif (is_array($value) || is_object($value)) {
                    $child = new \DOMElement(is_int($name) ? 'item' : $name);
                    $element->appendChild($child);
                    $this->buildXml($child, $value);
                } else {
                    $child = new \DOMElement(is_int($name) ? 'item' : $name);
                    $element->appendChild($child);
                    $child->appendChild(new \DOMText((string)$value));
                }
            }
        } elseif (is_object($data)) {
            $child = new \DOMElement(StringHelper::basename(get_class($data)));
            $element->appendChild($child);
            $array = [];
            foreach ($data as $name => $value) {
                $array[$name] = $value;
            }
            $this->buildXml($child, $array);
        } else {
            $element->appendChild(new \DOMText((string)$data));
        }
    }
}
