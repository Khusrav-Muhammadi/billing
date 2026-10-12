<?php

namespace Tests\Unit;

use App\Console\Commands\PointLogBackHostToNewCommand;
use Tests\TestCase;

class PointLogBackHostToNewCommandTest extends TestCase
{
    public function test_old_back_host_gets_new_suffix(): void
    {
        $this->assertSame(
            'https://sham-new-back.shamcrm.com/api/messengerSettings/site/webhook',
            PointLogBackHostToNewCommand::rewrite(
                'https://sham-back.shamcrm.com/api/messengerSettings/site/webhook'
            )
        );

        $this->assertSame(
            'https://baxtiyorjongaziyev-new-back.shamcrm.com/api/organization/storage-quota',
            PointLogBackHostToNewCommand::rewrite(
                'https://baxtiyorjongaziyev-back.shamcrm.com/api/organization/storage-quota'
            )
        );
    }

    public function test_host_that_already_has_new_is_left_alone(): void
    {
        $url = 'https://fingroupcrm-new-back.shamcrm.com/api/organization';

        $this->assertSame($url, PointLogBackHostToNewCommand::rewrite($url));
    }
}
