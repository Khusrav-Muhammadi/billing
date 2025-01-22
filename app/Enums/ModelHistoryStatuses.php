<?php

namespace App\Enums;

enum ModelHistoryStatuses :string
{
    case CREATED = 'Создан';

    case UPDATED = 'Изменен';

    case DELETED = 'Удален';

    case RESTORED = 'Восстановлен';

    case FORCE_DELETED = 'Очищен';


}
