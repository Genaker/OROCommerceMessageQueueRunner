<?php

namespace Genaker\Bundle\MessageQueueDebugBundle\Tests\Unit\Bundle;

use Genaker\Bundle\MessageQueueDebugBundle\GenakerMessageQueueDebugBundle;
use PHPUnit\Framework\TestCase;

class GenakerMessageQueueDebugBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        $bundle = new GenakerMessageQueueDebugBundle();

        self::assertInstanceOf(GenakerMessageQueueDebugBundle::class, $bundle);
    }

    public function testBundleHasCorrectName(): void
    {
        $bundle = new GenakerMessageQueueDebugBundle();

        self::assertSame('GenakerMessageQueueDebugBundle', $bundle->getName());
    }
}
