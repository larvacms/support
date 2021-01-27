<?php
/**
 * @copyright Copyright (c) 2018 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larvacent.com/
 * @license http://www.larvacent.com/license/
 */

namespace LarvaCMS\Support;

use GuzzleHttp\Psr7\Response;
use LarvaCMS\Support\Exception\InvalidCallException;
use LarvaCMS\Support\Exception\UnknownMethodException;
use LarvaCMS\Support\Exception\UnknownPropertyException;
use Psr\Http\Message\ResponseInterface;

/**
 * 响应类
 * @property-read array $data
 * @mixin Response
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class HttpResponse
{
    /**
     * @var ResponseInterface
     */
    protected $rawResponse;

    /**
     * @var string|null raw content
     */
    private $_content;

    /**
     * @var mixed
     */
    private $_data = null;

    /**
     * Response constructor.
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function __construct(ResponseInterface $response)
    {
        $this->rawResponse = $response;
        $this->_content = (string)$this->rawResponse->getBody()->getContents();
    }

    /**
     * @return ResponseInterface
     */
    public function getRawResponse(): ResponseInterface
    {
        return $this->rawResponse;
    }

    /**
     * @param string $directory
     * @param string $filename
     * @param bool $appendSuffix
     *
     * @return bool|int
     */
    public function save(string $directory, string $filename = '', bool $appendSuffix = true)
    {
        $this->rawResponse->getBody()->rewind();

        $directory = rtrim($directory, '/');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true); // @codeCoverageIgnore
        }

        if (!is_writable($directory)) {
            throw new \InvalidArgumentException(sprintf("'%s' is not writable.", $directory));
        }

        if (empty($this->_content) || '{' === $this->_content[0]) {
            throw new \RuntimeException('Invalid media response content.');
        }

        if (empty($filename)) {
            if (preg_match('/filename="(?<filename>.*?)"/', $this->getHeaderLine('Content-Disposition'), $match)) {
                $filename = $match['filename'];
            } else {
                $filename = md5($this->_content);
            }
        }

        if ($appendSuffix && empty(pathinfo($filename, PATHINFO_EXTENSION))) {
            $filename .= FileHelper::getStreamExt($this->_content);
        }

        file_put_contents($directory . '/' . $filename, $this->_content);

        return $filename;
    }

    /**
     * @param string $directory
     * @param string $filename
     * @param bool $appendSuffix
     *
     * @return bool|int
     */
    public function saveAs(string $directory, string $filename, bool $appendSuffix = true)
    {
        return $this->save($directory, $filename, $appendSuffix);
    }

    /**
     * 获取服务器类型
     * @return string
     */
    public function getServer(): string
    {
        if ($this->hasHeader('Server')) {
            return $this->getHeaderLine('Server');
        }
        return 'Unknown';
    }

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return $this->rawResponse->getHeaderLine('Content-Type');
    }

    /**
     * Returns the data fields, parsed from raw content.
     * @return array content data fields.
     */
    public function getData()
    {
        if (!$this->_data) {
            $contentType = $this->getContentType();
            $format = $this->detectFormatByContentType($contentType);
            if ($format === null) {
                $format = $this->detectFormatByContent($this->getContent());
            }
            switch ($format) {
                case 'json':
                    $this->_data = Json::decode($this->getContent());
                    break;
                case 'urlencoded':
                    $data = [];
                    parse_str($this->getContent(), $data);
                    $this->_data = $data;
                    break;
                case 'xml':
                    if (preg_match('/charset=(.*)/i', $contentType, $matches)) {
                        $encoding = $matches[1];
                    } else {
                        $encoding = 'UTF-8';
                    }
                    $dom = new \DOMDocument('1.0', $encoding);
                    $dom->loadXML($this->getContent(), LIBXML_NOCDATA);
                    $this->_data = $this->convertXmlToArray(simplexml_import_dom($dom->documentElement));
                    break;
            }
        }
        return $this->_data;
    }

    /**
     * Returns HTTP message raw content.
     * @return string raw body.
     */
    public function getContent()
    {
        return $this->_content;
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     * @param string $key Specific metadata to retrieve.
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata($key = null)
    {
        return $this->rawResponse->getBody()->getMetadata($key);
    }

    /**
     * Checks if response status code is OK (status code = 20x)
     * @return bool whether response is OK.
     */
    public function isOk(): bool
    {
        return strncmp('20', $this->getStatusCode(), 2) === 0;
    }

    /**
     * 是否是有效的响应码
     *
     * @return bool
     */
    public function isInvalid(): bool
    {
        return $this->getStatusCode() < 100 || $this->getStatusCode() >= 600;
    }

    /**
     * 是否是重定向响应
     *
     * @return bool
     */
    public function isRedirection(): bool
    {
        return $this->getStatusCode() >= 300 && $this->getStatusCode() < 400;
    }

    /**
     * 是否请求客户端错误
     *
     * @return bool
     */
    public function isClientError(): bool
    {
        return $this->getStatusCode() >= 400 && $this->getStatusCode() < 500;
    }

    /**
     * 服务端是否发生错误
     *
     * @return bool
     */
    public function isServerError(): bool
    {
        return $this->getStatusCode() >= 500 && $this->getStatusCode() < 600;
    }

    /**
     * 是否是403
     *
     * @return bool
     */
    public function isForbidden(): bool
    {
        return $this->getStatusCode() == 403;
    }

    /**
     * 是否是404
     *
     * @return bool
     */
    public function isNotFound(): bool
    {
        return $this->getStatusCode() == 404;
    }

    /**
     * 是否是空响应
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return in_array($this->getStatusCode(), [201, 204, 304]);
    }

    /**
     * 获取字符型的内容
     * @return string
     */
    public function toString()
    {
        return $this->getContent();
    }

    /**
     * @param string $name
     * @param array $params
     * @return mixed
     * @throws UnknownMethodException
     */
    public function __call(string $name, array $params)
    {
        if (method_exists($this->rawResponse, $name)) {
            return call_user_func_array([$this->rawResponse, $name], $params);
        }
        throw new UnknownMethodException('Calling unknown method: ' . get_class($this) . "::$name()");
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Returns the value of an object property.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$value = $object->property;`.
     * @param string $name the property name
     * @return mixed the property value
     * @throws UnknownPropertyException if the property is not defined
     * @throws InvalidCallException if the property is write-only
     * @see __set()
     */
    public function __get(string $name)
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter();
        } elseif (method_exists($this, 'set' . $name)) {
            throw new InvalidCallException('Getting write-only property: ' . get_class($this) . '::' . $name);
        }

        throw new UnknownPropertyException('Getting unknown property: ' . get_class($this) . '::' . $name);
    }

    /**
     * Sets value of an object property.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$object->property = $value;`.
     * @param string $name the property name or the event name
     * @param mixed $value the property value
     * @throws UnknownPropertyException if the property is not defined
     * @throws InvalidCallException if the property is read-only
     * @see __get()
     */
    public function __set(string $name, $value)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            $this->$setter($value);
        } elseif (method_exists($this, 'get' . $name)) {
            throw new InvalidCallException('Setting read-only property: ' . get_class($this) . '::' . $name);
        } else {
            throw new UnknownPropertyException('Setting unknown property: ' . get_class($this) . '::' . $name);
        }
    }

    /**
     * Converts XML document to array.
     * @param string|\SimpleXMLElement $xml xml to process.
     * @return array XML array representation.
     */
    protected function convertXmlToArray($xml)
    {
        if (is_string($xml)) {
            $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        }
        $result = (array)$xml;
        foreach ($result as $key => $value) {
            if (!is_scalar($value)) {
                $result[$key] = $this->convertXmlToArray($value);
            }
        }
        return $result;
    }

    /**
     * Detects format from headers.
     * @param string $contentType source content-type.
     * @return null|string format name, 'null' - if detection failed.
     */
    protected function detectFormatByContentType(string $contentType)
    {
        if (!empty($contentType)) {
            if (stripos($contentType, 'json') !== false) {
                return 'json';
            }
            if (stripos($contentType, 'urlencoded') !== false) {
                return 'urlencoded';
            }
            if (stripos($contentType, 'xml') !== false) {
                return 'xml';
            }
        }
        return null;
    }

    /**
     * Detects response format from raw content.
     * @param string $content raw response content.
     * @return null|string format name, 'null' - if detection failed.
     */
    protected function detectFormatByContent(string $content)
    {
        if (preg_match('/^\\{.*\\}$/is', $content)) {
            return 'json';
        }
        if (preg_match('/^([^=&])+=[^=&]+(&[^=&]+=[^=&]+)*$/', $content)) {
            return 'urlencoded';
        }
        if (preg_match('/^<.*>$/s', $content)) {
            return 'xml';
        }
        return null;
    }
}
