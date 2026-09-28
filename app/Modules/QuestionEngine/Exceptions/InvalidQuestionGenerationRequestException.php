<?php

namespace App\Modules\QuestionEngine\Exceptions;

use Exception;

class InvalidQuestionGenerationRequestException extends Exception
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public array $errors = [],
        string $message = 'Invalid question generation request parameters.',
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        $fullMessage = !empty($errors)
            ? $message.' Errors: '.implode('; ', $errors)
            : $message;

        parent::__construct($fullMessage, $code, $previous);
    }
}
