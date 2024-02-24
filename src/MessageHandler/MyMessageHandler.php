<?php

namespace App\MessageHandler;

use App\Message\MyMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MyMessageHandler
{
    public function __invoke(MyMessage $message): void
    {
        // do something with your message
    }
}
