<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Ожидаемый сбой при выдаче демо. Сообщение показывается пользователю,
 * причина попадает в логи и в ответ API.
 */
class DemoProvisioningException extends RuntimeException
{
    /**
     * @param  string  $reason    Машинный код причины.
     * @param  string  $message   Текст для пользователя.
     * @param  bool    $retryable Имеет ли смысл повторить джобу позже.
     * @param  bool    $rollback  Можно ли удалить созданные записи биллинга.
     *                            False — если аккаунт уже работоспособен.
     */
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly bool $retryable = false,
        public readonly bool $rollback = true,
    ) {
        parent::__construct($message);
    }
}
