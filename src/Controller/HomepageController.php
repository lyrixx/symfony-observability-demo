<?php

namespace App\Controller;

use App\Dto\User;
use App\Message\MyMessage;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[WithMonologChannel('homepage')]
class HomepageController extends AbstractController
{
    /**
     * Tempos offered on the log generator page, with their human label.
     *
     * @var array<string, string>
     */
    private const TEMPOS = [
        'now' => 'right now',
        '30m' => 'the last 30 minutes',
        '1h' => 'the last hour',
        '6h' => 'the last 6 hours',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('/', name: 'homepage')]
    public function homepage(): Response
    {
        $this->logger->info('Homepage visited.', [
            'user' => new User(id: 1, name: 'John Doe'),
        ]);

        $this->bus->dispatch(new MyMessage('Look! I created a message!'));

        return $this->render('homepage/homepage.html.twig', [
            'controller_name' => 'HomepageController',
        ]);
    }

    #[Route('/logs', name: 'logs')]
    public function logs(): Response
    {
        return $this->render('homepage/logs.html.twig', [
            'tempos' => self::TEMPOS,
        ]);
    }

    #[Route('/logs/generate/{tempo}', name: 'generate_logs', requirements: ['tempo' => 'now|30m|1h|6h'])]
    public function generateLogs(string $tempo): Response
    {
        [$seconds, $count] = match ($tempo) {
            'now' => [0, random_int(1, 100)],
            '30m' => [1_800, 150],
            '1h' => [3_600, 250],
            '6h' => [21_600, 600],
            default => throw new \UnexpectedValueException("Unknown tempo \"{$tempo}\"."),
        };

        // Emit oldest-first: Loki (and log stores in general) rejects or
        // mishandles out-of-order entries within the same stream.
        $offsets = [];
        for ($i = 0; $i < $count; ++$i) {
            $offsets[] = $seconds > 0 ? random_int(0, $seconds) : 0;
        }
        rsort($offsets);

        $now = new \DateTimeImmutable();
        foreach ($offsets as $offset) {
            $this->emitLog($now->modify(\sprintf('-%d seconds', $offset)));
        }

        $this->addFlash('success', \sprintf('%d logs generated, spread over %s!', $count, self::TEMPOS[$tempo]));

        return $this->redirectToRoute('homepage');
    }

    #[Route('/exception', name: 'exception')]
    public function exception(): Response
    {
        $this->logger->error('Oups, somethings wrong happened!', [
            'exception' => new \RuntimeException('This is a random exception.'),
        ]);

        $this->addFlash('success', 'Logs emitted!');

        return $this->redirectToRoute('homepage');
    }

    private function emitLog(\DateTimeImmutable $at): void
    {
        $level = match (random_int(1, 8)) {
            1 => LogLevel::DEBUG,
            2 => LogLevel::INFO,
            3 => LogLevel::NOTICE,
            4 => LogLevel::WARNING,
            5 => LogLevel::ERROR,
            6 => LogLevel::CRITICAL,
            7 => LogLevel::ALERT,
            8 => LogLevel::EMERGENCY,
        };

        $this->logger->log($level, \sprintf('A random string at level %s - %s.', $level, bin2hex(random_bytes(15))), [
            'backdate_to' => $at,
        ]);
    }
}
