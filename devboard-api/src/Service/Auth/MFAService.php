<?php

namespace App\Service\Auth;

use App\Entity\WpUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment;

class MFAService
{
    private const MFA_CODE_LENGTH = 6;
    private const MFA_CODE_EXPIRY = 600; // 10 minutes
    private const MFA_CACHE_PREFIX = 'mfa_code_';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private ParameterBagInterface $params,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        private Environment $twig
    ) {}

    public function generateAndSendMFACode(WpUser $user): void
    {
        // Generate a 6-digit code
        $code = str_pad((string) random_int(0, 999999), self::MFA_CODE_LENGTH, '0', STR_PAD_LEFT);
        
        // Store in cache with user's email as key
        $cacheKey = self::MFA_CACHE_PREFIX . $user->getEmail();
        $this->logger->info('MFA Debug: Storing code', [
            'email' => $user->getEmail(),
            'code' => $code,
            'cacheKey' => $cacheKey
        ]);

        try {
            // Clear any existing code first
            $this->cache->delete($cacheKey);
            
            // Store the new code
            $this->cache->get(
                $cacheKey,
                function (ItemInterface $item) use ($code) {
                    $item->expiresAfter(self::MFA_CODE_EXPIRY);
                    $this->logger->info('MFA Debug: Code stored in cache', [
                        'expiresIn' => self::MFA_CODE_EXPIRY
                    ]);
                    return $code;
                }
            );
        } catch (\Exception $e) {
            $this->logger->error('MFA Debug: Failed to store code in cache', [
                'email' => $user->getEmail(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        // Send email with the code
        $email = (new Email())
            ->from($this->params->get('app.mailer_from'))
            ->to($user->getEmail())
            ->subject('Your DevBoard Login Verification Code')
            ->html($this->twig->render('emails/mfa_code.html.twig', ['code' => $code]));

        $this->mailer->send($email);
        $this->logger->info('MFA Debug: Email sent', [
            'email' => $user->getEmail()
        ]);
    }

    public function verifyMFACode(string $email, string $code): bool
    {
        $cacheKey = self::MFA_CACHE_PREFIX . $email;
        $this->logger->info('MFA Debug: Verifying code', [
            'email' => $email,
            'cacheKey' => $cacheKey
        ]);

        try {
            $storedCode = $this->cache->get($cacheKey, function (ItemInterface $item) {
                $this->logger->info('MFA Debug: No code found in cache', [
                    'cacheKey' => $item->getKey()
                ]);
                return null;
            });

            if ($storedCode === null) {
                $this->logger->info('MFA Debug: Code not found in cache', [
                    'email' => $email
                ]);
                return false;
            }

            $this->logger->info('MFA Debug: Code comparison', [
                'email' => $email,
                'storedCode' => $storedCode,
                'providedCode' => $code
            ]);

            if ($storedCode === $code) {
                $this->cache->delete($cacheKey);
                $this->logger->info('MFA Debug: Code verified successfully', [
                    'email' => $email
                ]);
                return true;
            }

            $this->logger->info('MFA Debug: Code mismatch', [
                'email' => $email
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('MFA Debug: Error verifying code', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
} 