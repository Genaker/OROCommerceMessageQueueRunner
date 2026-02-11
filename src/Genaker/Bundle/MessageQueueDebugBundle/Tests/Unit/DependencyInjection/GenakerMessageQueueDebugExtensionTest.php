<?php

namespace Genaker\Bundle\MessageQueueDebugBundle\Tests\Unit\DependencyInjection;

use Genaker\Bundle\MessageQueueDebugBundle\DependencyInjection\GenakerMessageQueueDebugExtension;
use Oro\Bundle\TestFrameworkBundle\Test\DependencyInjection\ExtensionTestCase;

class GenakerMessageQueueDebugExtensionTest extends ExtensionTestCase
{
    public function testLoad(): void
    {
        $this->loadExtension(new GenakerMessageQueueDebugExtension());

        $this->assertDefinitionsLoaded([
            'Genaker\Bundle\MessageQueueDebugBundle\Command\MessageQueueListCommand',
            'Genaker\Bundle\MessageQueueDebugBundle\Command\MessageQueueProcessCommand',
        ]);
    }

    public function testGetAlias(): void
    {
        $extension = new GenakerMessageQueueDebugExtension();

        self::assertSame('genaker_message_queue_debug', $extension->getAlias());
    }
}
