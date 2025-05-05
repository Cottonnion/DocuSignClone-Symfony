<?php

namespace App\Service\User;

use App\Entity\WpUser;
use App\Repository\WpUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Monolog\Attribute\AsChannel;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsChannel('user')]
class PasswordResetService
{
    private const TOKEN_EXPIRY = 3600; // 1 hour
    private const MAX_RESET_REQUESTS = 3;
    private const RESET_WINDOW = 3600; // 1 hour
    private const MIN_PASSWORD_CHANGE_INTERVAL = 86400; // 24 hours

    private $emailTokenCache;
    private $rateLimitCache;

    public function __construct(
        private WpUserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $PasswordHasher,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
        private LoggerInterface $logger,
        private ParameterBagInterface $params
    ) {
        $this->emailTokenCache = new FilesystemAdapter('password_reset', 0, sys_get_temp_dir());
        $this->rateLimitCache = new FilesystemAdapter('rate_limit', 0, sys_get_temp_dir());
    }

    public function sendResetEmail(string $email): void
    {
        // Check rate limiting
        $this->checkRateLimit($email);

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            // Log failed attempt
            $this->logger->warning('Password reset attempt for non-existent email', [
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'timestamp' => new \DateTime()
            ]);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->emailTokenCache->save(
            $this->emailTokenCache->getItem($token)
                ->set($user->getId())
                ->expiresAfter(self::TOKEN_EXPIRY)
        );

        $resetUrl = $this->urlGenerator->generate('reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        
        $body = $this->twig->render('emails/password_reset.html.twig', [
            'resetUrl' => $resetUrl
        ]);

        $email = (new Email())
            ->from('noreply@devboard.com')
            ->to($user->getEmail())
            ->subject('Password Reset Request - DevBoard')
            ->priority(Email::PRIORITY_HIGH)
            ->html($body);

        $this->mailer->send($email);

        // Log successful reset request
        $this->logger->info('Password reset email sent', [
            'user_id' => $user->getId(),
            'email' => $email,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => new \DateTime()
        ]);
    }

    public function resetPassword(string $token, string $newPassword): array
    {
        $item = $this->emailTokenCache->getItem($token);

        if (!$item->isHit()) {
            $this->logger->warning('Invalid password reset token used', [
                'token' => $token,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'timestamp' => new \DateTime()
            ]);
            return [
                'status' => 'error',
                'type' => 'expired_reset_token',
                'message' => 'Invalid or expired reset token'
            ];
        }

        $userId = $item->get();
        $user = $this->userRepository->find($userId);

        if (!$user) {
            $this->logger->error('User not found for valid reset token', [
                'user_id' => $userId,
                'token' => $token,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'timestamp' => new \DateTime()
            ]);
            return [
                'status' => 'error',
                'type' => 'user_not_found',
                'message' => 'User not found'
            ];
        }

        // Check if password was recently changed
        $lastChange = $user->getLastPasswordChange();
        if ($lastChange && (time() - $lastChange->getTimestamp() < self::MIN_PASSWORD_CHANGE_INTERVAL)) {
            $this->logger->warning('Password reset attempted too soon after last change', [
                'user_id' => $user->getId(),
                'last_change' => $lastChange->format('Y-m-d H:i:s'),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'timestamp' => new \DateTime()
            ]);
            return [
                'status' => 'error',
                'type' => 'too_soon',
                'message' => 'Password was recently changed. Please wait before changing it again.'
            ];
        }

        $user->setPassword($this->PasswordHasher->hashPassword($user, $newPassword));
        $user->setLastPasswordChange(new \DateTime());
        $this->entityManager->flush();

        $this->emailTokenCache->delete($token);

        // Send confirmation email
        $this->sendPasswordChangeNotification($user);

        // Log successful password reset
        $this->logger->info('Password reset successful', [
            'user_id' => $user->getId(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => new \DateTime()
        ]);

        return [
            'status' => 'success',
            'type' => 'password_reset_successfully',
            'message' => 'Password has been reset successfully'
        ];
    }

    private function checkRateLimit(string $email): void
    {
        $key = 'reset_attempts_' . $email;
        $attempts = $this->rateLimitCache->getItem($key)->get() ?? 0;
        
        if ($attempts >= self::MAX_RESET_REQUESTS) {
            $this->logger->warning('Rate limit exceeded for password reset', [
                'email' => $email,
                'attempts' => $attempts,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'timestamp' => new \DateTime()
            ]);
            throw new TooManyRequestsHttpException(
                self::RESET_WINDOW,
                'Too many password reset attempts. Please try again later.'
            );
        }
        
        $this->rateLimitCache->save(
            $this->rateLimitCache->getItem($key)
                ->set($attempts + 1)
                ->expiresAfter(self::RESET_WINDOW)
        );
    }

    private function sendPasswordChangeNotification(WpUser $user): void
    {
        $body = $this->twig->render('emails/password_changed.html.twig');
        
        $email = (new Email())
            ->from($this->params->get('app.mailer_from'))
            ->to($user->getEmail())
            ->subject('Your DevBoard Password Has Been Changed')
            ->html($body);
            
        $this->mailer->send($email);
        
        $this->logger->info('Password change notification sent', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);
    }
}