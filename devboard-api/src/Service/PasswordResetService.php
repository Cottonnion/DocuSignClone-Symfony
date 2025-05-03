<?php

namespace App\Service;

use App\Entity\WpUser;
use App\Repository\WpUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class PasswordResetService
{

    private const TOKEN_EXPIRY  =   3600;
    private $emailTokenCache;

    public function __construct(
        private WpUserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $PasswordHasher,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig
    ){
        $this->emailTokenCache = new FilesystemAdapter('password_reset', 0, sys_get_temp_dir());
    }

    public function sendResetEmail(string $email): void
    {
        $user = $this->userRepository->findOneBy(['email' =>  $email]);

        if(!$user){
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->emailTokenCache->save(
            $this->emailTokenCache->getItem($token)
            ->set($user->getId())
            ->expiresAfter(self::TOKEN_EXPIRY)
        );

        $resetUrl   =   $this->urlGenerator->generate('reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        $body       =   $this->twig->render('emails/password_reset.html.twig', [
            'resetUrl'  =>  $resetUrl
        ]);

        $email = (new Email())
            ->from('noreply@devboard.com')
            ->to($user->getEmail())
            ->subject('Password Reset Request - DevBoard')
            ->priority(Email::PRIORITY_HIGH)
            ->html($body);

        $this->mailer->send($email);
    }

    public function resetPassword(string $token, string $newPassword): array
    {
        $item = $this->emailTokenCache->getItem($token);

        if(!$item->isHit()){
            return [
                'status'    =>  'error',
                'type'      =>  'expired_reset_token',
                'message'   =>  'Invalid or expired reset token'
            ];
        }

        $userId = $item->get();
        $user = $this->userRepository->find($userId);

        if(!$user){
            return [
                'status'    =>  'error',
                'type'      =>  'user_not_found',
                'message'   =>  'User not found'
            ];
        }

        $user->setPassword($this->PasswordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();

        $this->emailTokenCache->delete($token);

        return [
            'status'    =>  'success',
            'type'      =>  'password_reset_succesfully',
            'message'   =>  'Password has been reset successfully'
        ];
    }
}