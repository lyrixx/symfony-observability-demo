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
        for ($i = 0, $l = random_int(1, 100); $i < $l; ++$i) {
            $this->emitLog();
        }

        $this->addFlash('success', 'Logs emitted!');

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

    private function emitLog(): void
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

        $this->logger->log($level, sprintf('A random string at level %s - %s.', $level, bin2hex(random_bytes(15))));
    }
}
