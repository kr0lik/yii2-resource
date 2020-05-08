<?php
namespace kr0lik\resource\Exception;

class ResourceException extends \Exception
{
    private $path;

    public function __construct(string $path, $message = '', $code = 0, Throwable $previous = null)
    {
        $this->path = $path;

        parent::__construct(message, $code , $previous);
    }
}
