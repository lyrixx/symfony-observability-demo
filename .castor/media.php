<?php

namespace media;

use Castor\Attribute\AsTask;
use Playwright\Playwright;

use function Castor\context;
use function Castor\io;
use function Castor\run;
use function Castor\variable;
use function worktree\get_worktree_name;
use function worktree\get_worktree_ports;

function get_playwright_dir(): string
{
    return variable('root_dir') . '/.castor/vendor/playwright-php/playwright/bin';
}

#[AsTask(description: 'Installs the Playwright browser on the host (one-time setup)')]
function install(): void
{
    io()->title('Installing the Playwright browser');

    $context = context()->withWorkingDirectory(get_playwright_dir());

    run(['npm', 'install', '--omit=dev'], context: $context);
    run(['npx', 'playwright', 'install', 'chromium'], context: $context);
}

#[AsTask(description: 'Regenerates the screenshots of the application used in the README', aliases: ['media'])]
function generate(): void
{
    io()->title('Generating the README screenshots');

    if (!is_dir(get_playwright_dir() . '/node_modules')) {
        install();
    }

    $httpsPort = getenv('PROJECT_HTTPS_PORT') ?: get_worktree_ports(get_worktree_name())['https'];
    $baseUrl = \sprintf('https://%s:%s', variable('root_domain'), $httpsPort);
    $mediaDir = variable('root_dir') . '/media';

    $context = Playwright::chromium([
        'headless' => true,
        'context' => [
            'viewport' => ['width' => 1440, 'height' => 1040],
            'deviceScaleFactor' => 2,
            'ignoreHTTPSErrors' => true,
        ],
    ]);

    $context->addInitScript(<<<'JS'
        document.addEventListener('DOMContentLoaded', () => {
            const style = document.createElement('style');
            style.textContent = '.sf-toolbar{display:none!important}';
            document.head.appendChild(style);
        });
        JS);

    $page = $context->newPage();

    // Feeds the dashboards, then leaves Vector the time to ship the logs.
    $page->goto($baseUrl . '/logs/generate/6h');
    sleep(5);

    io()->section('Homepage');
    $page->goto($baseUrl . '/');
    $page->frameLocator('iframe[title^="Log volume"]')->locator('canvas')->first()->waitFor(['timeout' => 60_000]);
    $page->frameLocator('iframe[title^="Recent logs"]')->locator('text=channel=')->first()->waitFor(['timeout' => 60_000]);
    // Grafana keeps drawing for a moment after the first paint of its panels.
    sleep(2);
    $page->screenshot($mediaDir . '/homepage.png', ['fullPage' => true]);

    io()->section('Log generator');
    // The left menu is as tall as the viewport: each viewport fits its page, so that the menu reaches the bottom.
    $page->setViewportSize(1440, 640);
    $page->goto($baseUrl . '/logs');
    $page->screenshot($mediaDir . '/logs.png', ['fullPage' => true]);

    $context->close();

    io()->success('Screenshots generated in media/homepage.png and media/logs.png.');
}
