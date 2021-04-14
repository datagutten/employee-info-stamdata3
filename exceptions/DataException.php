<?php
/**
 * Created by PhpStorm.
 * User: abi
 * Date: 08.08.2019
 * Time: 12:27
 */

namespace storfollo\EmployeeInfo\exceptions;

use Exception;

class DataException extends Exception
{
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}