<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomepageControllerTest extends WebTestCase
{
    public function testHomepageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Observability Demo Application');
    }

    public function testLogsPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/logs');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href$="/logs/generate/6h"]');
    }

    /**
     * @dataProvider provideGenerateLogsRedirectsToHomepageCases
     */
    public function testGenerateLogsRedirectsToHomepage(string $tempo): void
    {
        $client = static::createClient();
        $client->request('GET', "/logs/generate/{$tempo}");

        self::assertResponseRedirects('/');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideGenerateLogsRedirectsToHomepageCases(): iterable
    {
        yield 'now' => ['now'];
        yield '30 minutes' => ['30m'];
        yield '1 hour' => ['1h'];
        yield '6 hours' => ['6h'];
    }

    public function testExceptionRedirectsToHomepage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/exception');

        self::assertResponseRedirects('/');
    }
}
