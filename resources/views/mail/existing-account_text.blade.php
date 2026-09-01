Здравствуйте, {{ $client->name }}!

Вы запросили доступ к shamCRM, однако для этого email уже существует аккаунт.
Ниже — основные данные вашей CRM:

@if($client->sub_domain)
Адрес CRM: {{ $client->crmHost() }}
Ссылка: <{{ $client->crmUrl() }}>
@endif
Email (логин): {{ $client->email }}
@if($client->phone)
Телефон: {{ $client->phone }}
@endif
@if($client->tariff?->name)
Тариф: {{ $client->tariff->name }}
@endif

По соображениям безопасности пароль не отображается в письме.

www.shamcrm.com
+998-55-588-81-00
