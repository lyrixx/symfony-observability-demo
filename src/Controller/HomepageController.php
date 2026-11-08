<?php

namespace App\Controller;

use App\Dto\User;
use App\Enum\LogTempo;
use App\Message\MyMessage;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

#[WithMonologChannel('homepage')]
class HomepageController extends AbstractController
{
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
            'tempos' => LogTempo::cases(),
        ]);
    }

    #[Route('/logs/generate/{tempo}', name: 'generate_logs', requirements: ['tempo' => new EnumRequirement(LogTempo::class)])]
    public function generateLogs(LogTempo $tempo): Response
    {
        $seconds = $tempo->windowInSeconds();
        $count = random_int($tempo->minLogCount(), $tempo->maxLogCount());

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

        $this->addFlash('success', \sprintf('%d logs generated, spread over %s!', $count, $tempo->label()));

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
