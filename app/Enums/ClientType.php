<?php

namespace App\Enums;

enum ClientType :string
{
     case LegalEntity = 'Юр.Лицо';
     case Individual = 'Физ.Лицо';
     case Entrepreneur = 'Ин.Предприниматель';
}
