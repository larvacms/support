<?php
/**
 * @copyright Copyright (c) 2018 Larva Information Technology Co., Ltd.
 * @link http://www.larvacent.com/
 * @license http://www.larvacent.com/license/
 */
namespace LarvaCMS\Support\Exception;

/**
 * Class RuntimeException
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class RuntimeException extends \RuntimeException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Runtime Exception';
    }
}
