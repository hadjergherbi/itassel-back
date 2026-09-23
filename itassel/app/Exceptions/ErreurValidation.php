<?php

namespace App\Exceptions;

use RuntimeException;

class ErreurValidation extends RuntimeException
{
    public function __construct(
        public readonly string $champ,
        string $message,
        public readonly ?string $codeErreur = null,
    ) {
        parent::__construct($message);
    }

    public function toResponse()
    {
        $corps = [
            'message' => $this->getMessage(),
            'errors'  => [$this->champ => [$this->getMessage()]],
        ];

        if ($this->codeErreur) {
            $corps['code'] = $this->codeErreur;
        }

        return response()->json($corps, 422);
    }
}
