<?php
namespace kr0lik\recource\Exception;

class ResourceExistsException extends ResourceException
{
    protected $message = 'Resource not exists.';
}
