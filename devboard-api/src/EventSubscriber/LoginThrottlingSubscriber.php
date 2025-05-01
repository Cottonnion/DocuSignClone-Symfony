<?php

namespace App\EventSubscriber;

use App\Security\LoginThrottlingHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class LoginThrottlingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoginThrottlingHandler $throttlingHandler
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $exception = $event->getException();
        
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $response = $this->throttlingHandler->handle($event->getRequest(), $exception);
            $event->setResponse($response);
        }
    }
} 