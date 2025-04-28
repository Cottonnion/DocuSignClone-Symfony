<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Doctrine\Common\EventSubscriber;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTCreatedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_jwt_created'   =>  'onJWTCreated',
        ];
    }

    public function onJWTCreated(JWTCreatedEvent $e): void
    {
        $user = $e->getUser();

        if($user instanceof UserInterface && method_exists($user, 'getEmail')){
            $payload = $e->getData();
            $payload['username'] = $user->getEmail();
            $payload['email']   = $user->getEmail();
            $e->setData($payload);
        }
    }
}