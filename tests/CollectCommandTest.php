<?php

namespace Monyxie\CollectTranslation\Tests;

use Monyxie\CollectTranslation\ServiceProvider;
use Orchestra\Testbench\TestCase;

class CollectCommandTest extends TestCase {
    protected function getPackageProviders($app) {
        return [ServiceProvider::class];
    }

    /**
     * Setup the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->artisan('translations:collect', ['-r' => true, '-y' => true]);
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app) {
        $app->setBasePath(__DIR__ . '/base_path');
    }

    /** @test */
    public function it_collects_the_translation_items() {
        $filename = __DIR__ . '/base_path/resources/lang/zh_cn/base.php';
        $this->assertFileExists($filename, 'Creates the output file');

        $generatedTranslations = require $filename;
        $expectedTranslations = [
            'ok' => 'base.ok',
            'error' => 'base.error',
        ];
        $this->assertEquals($generatedTranslations, $expectedTranslations, 'Generates the correct translation items');
    }
}
