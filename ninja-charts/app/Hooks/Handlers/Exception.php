<?php

namespace NinjaCharts\App\Hooks\Handlers;

class Exception
{
    protected $handlers = [
        'NinjaCharts\Framework\Foundation\ForbiddenException'       => 'handleForbiddenException',
        'NinjaCharts\Framework\Validator\ValidationException'       => 'handleValidationException',
        'NinjaCharts\Framework\Foundation\UnAuthorizedException'    => 'handleUnAuthorizedException',
        'NinjaCharts\Framework\Database\Orm\ModelNotFoundException' => 'handleModelNotFoundException',
    ];

    public function handle($e)
    {
        foreach ($this->handlers as $key => $value) {
            if ($e instanceof $key) {
                return $this->{$value}($e);
            }
        }
    }

    public function handleModelNotFoundException($e)
    {
        return $this->sendError([
            'message' => $e->getMessage()
        ], $e->getCode() ?: 404);
    }

    public function handleUnAuthorizedException($e)
    {
        return $this->sendError([
            'message' => $e->getMessage()
        ], $e->getCode() ?: 401);
    }

    public function handleForbiddenException($e)
    {
        return $this->sendError([
            'message' => $e->getMessage()
        ], $e->getCode() ?: 403);
    }

    public function handleValidationException($e)
    {
        return $this->sendError([
            'message' => $e->getMessage(),
            'errors'  => $e->errors()
        ], $e->getCode() ?: 422);
    }

    public function sendError($data)
    {
        return ninjaCharts('response')->sendError($data);
    }
}
