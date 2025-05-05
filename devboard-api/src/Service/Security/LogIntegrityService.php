<?php

namespace App\Service\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Entity\WpUser;

/**
 * Service responsible for ensuring log integrity through cryptographic hashing.
 * 
 * This service adds integrity protection to log entries by generating HMAC hashes
 * of log messages and their context. It helps detect tampering with log files
 * and ensures the authenticity of log entries.
 * 
 * Features:
 * - HMAC-based integrity verification
 * - Automatic timestamp and user context
 * - IP address tracking
 * - Secure hash generation
 * - Log entry verification
 */
class LogIntegrityService
{
    /**
     * The hashing algorithm used for generating HMACs.
     */
    private const HASH_ALGO = 'sha256';

    /**
     * @param LoggerInterface $logger The logger instance to use
     * @param TokenStorageInterface $tokenStorage The token storage to get current user
     * @param string $secretKey The secret key used for HMAC generation
     */
    public function __construct(
        private LoggerInterface $logger,
        private TokenStorageInterface $tokenStorage,
        private string $secretKey
    ) {}

    /**
     * Logs a message with integrity protection.
     * 
     * This method adds a cryptographic hash to the log entry to ensure its integrity.
     * It automatically includes:
     * - Precise timestamp
     * - Current user ID (if authenticated)
     * - Client IP address
     * - HMAC hash of the entire entry
     *
     * @param string $message The log message
     * @param array $context Additional context data
     * @return void
     */
    public function logWithIntegrity(string $message, array $context = []): void
    {
        // Add timestamp and user info
        $context['timestamp'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $context['user_id'] = $this->getCurrentUserId();
        $context['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Generate hash
        $hash = $this->generateHash($message, $context);
        $context['integrity_hash'] = $hash;

        $this->logger->info($message, $context);
    }

    /**
     * Verifies the integrity of a log entry.
     * 
     * This method checks if a log entry has been tampered with by:
     * 1. Extracting the stored hash
     * 2. Regenerating the hash with the same data
     * 3. Comparing the two hashes
     *
     * @param string $logEntry The log entry to verify (JSON format)
     * @return bool True if the log entry is valid, false otherwise
     */
    public function verifyLogIntegrity(string $logEntry): bool
    {
        $data = json_decode($logEntry, true);
        if (!$data) {
            return false;
        }

        $hash = $data['context']['integrity_hash'] ?? null;
        if (!$hash) {
            return false;
        }

        unset($data['context']['integrity_hash']);
        $expectedHash = $this->generateHash($data['message'], $data['context']);

        return hash_equals($hash, $expectedHash);
    }

    /**
     * Generates an HMAC hash for a log entry.
     * 
     * The hash is generated using:
     * - The log message
     * - All context data
     * - A secret key
     * 
     * @param string $message The log message
     * @param array $context The context data
     * @return string The generated HMAC hash
     */
    private function generateHash(string $message, array $context): string
    {
        $dataToHash = [
            'message' => $message,
            'context' => $context,
            'key' => $this->secretKey
        ];

        return hash_hmac(
            self::HASH_ALGO,
            json_encode($dataToHash),
            $this->secretKey
        );
    }

    /**
     * Gets the current user's ID if authenticated.
     * 
     * @return int|null The user ID or null if not authenticated
     */
    private function getCurrentUserId(): ?int
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        $user = $token->getUser();
        if (!$user instanceof WpUser) {
            return null;
        }

        return $user->getId();
    }
} 