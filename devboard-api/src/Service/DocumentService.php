<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\WpUser as User;
use App\Repository\DocumentRepository;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;

class DocumentService
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private AuditLogRepository $auditLogRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function getDocuments(?string $status = null, ?bool $isTemplate = null, ?string $createdBy = null): array
    {
        return $this->documentRepository->findByFilters($status, $isTemplate, $createdBy);
    }

    public function createDocument(string $content, UserInterface $user): Document
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('User must be an instance of App\Entity\User');
        }

        $document = $this->serializer->deserialize($content, Document::class, 'json');
        $document->setCreatedBy($user);
        
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    public function updateDocument(Document $document, string $content): Document
    {
        $this->serializer->deserialize(
            $content,
            Document::class,
            'json',
            ['object_to_populate' => $document]
        );
        
        $this->entityManager->flush();

        return $document;
    }

    public function deleteDocument(Document $document): void
    {
        $this->entityManager->remove($document);
        $this->entityManager->flush();
    }

    public function getAuditLogs(Document $document): array
    {
        return $this->auditLogRepository->findBy(
            ['document' => $document],
            ['timestamp' => 'DESC']
        );
    }

    public function sendDocument(Document $document): Document
    {
        if ($document->getStatus() !== 'draft') {
            throw new \InvalidArgumentException('Document must be in draft status');
        }

        $document->setStatus('sent');
        $this->entityManager->flush();

        foreach ($document->getSignatories() as $signatory) {
            $this->sendEmail(
                $signatory->getEmail(),
                'Document Signing Request: ' . $document->getTitle(),
                'document_notifications.html.twig',
                'document_sent',
                [
                    'document' => $document,
                    'signatory' => $signatory,
                    'signing_url' => $this->generateSigningUrl($document, $signatory)
                ]
            );
        }

        return $document;
    }

    public function remindSignatories(Document $document): void
    {
        if ($document->getStatus() !== 'sent') {
            throw new \InvalidArgumentException('Document must be in sent status');
        }

        foreach ($document->getSignatories() as $signatory) {
            if (!$signatory->isSigned()) {
                $this->sendEmail(
                    $signatory->getEmail(),
                    'Reminder: Please sign document - ' . $document->getTitle(),
                    'document_notifications.html.twig',
                    'document_reminder',
                    [
                        'document' => $document,
                        'signatory' => $signatory,
                        'signing_url' => $this->generateSigningUrl($document, $signatory)
                    ]
                );
            }
        }
    }

    public function notifyDocumentCompleted(Document $document): void
    {
        $this->sendEmail(
            $document->getCreatedBy()->getEmail(),
            'Document Fully Signed: ' . $document->getTitle(),
            'document_notifications.html.twig',
            'document_completed',
            [
                'document' => $document,
                'user' => $document->getCreatedBy(),
                'download_url' => $this->urlGenerator->generate('documents_download', ['id' => $document->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ]
        );
    }

    public function notifyDocumentRejected(Document $document, UserInterface $rejector, string $reason): void
    {
        $this->sendEmail(
            $document->getCreatedBy()->getEmail(),
            'Document Rejected: ' . $document->getTitle(),
            'document_notifications.html.twig',
            'document_rejected',
            [
                'document' => $document,
                'user' => $document->getCreatedBy(),
                'rejector' => $rejector,
                'rejection_reason' => $reason,
                'document_url' => $this->urlGenerator->generate('documents_get', ['id' => $document->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ]
        );
    }

    public function notifySignatorySigned(Document $document, UserInterface $signatory): void
    {
        $remainingSignatories = count($document->getSignatories()) - $document->getSignedCount();
        
        $this->sendEmail(
            $document->getCreatedBy()->getEmail(),
            'Document Signed: ' . $document->getTitle(),
            'document_notifications.html.twig',
            'signatory_signed',
            [
                'document' => $document,
                'user' => $document->getCreatedBy(),
                'signatory' => $signatory,
                'remaining_signatories' => $remainingSignatories,
                'document_url' => $this->urlGenerator->generate('documents_get', ['id' => $document->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ]
        );
    }

    public function sendExpiryWarning(Document $document, int $daysRemaining): void
    {
        $this->sendEmail(
            $document->getCreatedBy()->getEmail(),
            'Document Expiry Warning: ' . $document->getTitle(),
            'document_notifications.html.twig',
            'document_expiry_warning',
            [
                'document' => $document,
                'user' => $document->getCreatedBy(),
                'days_remaining' => $daysRemaining,
                'document_url' => $this->urlGenerator->generate('documents_get', ['id' => $document->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ]
        );
    }

    private function sendEmail(string $to, string $subject, string $template, string $block, array $context): void
    {
        $html = $this->twig->render($template, $context);
        
        $email = (new Email())
            ->from('noreply@devboard.com')
            ->to($to)
            ->subject($subject)
            ->html($html);

        $this->mailer->send($email);
    }

    private function generateSigningUrl(Document $document, $signatory): string
    {
        return $this->urlGenerator->generate(
            'documents_sign',
            [
                'id' => $document->getId(),
                'signatoryId' => $signatory->getId(),
                'token' => $this->generateSigningToken($document, $signatory)
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function generateSigningToken(Document $document, $signatory): string
    {
        // TODO: Implement secure token generation
        return hash('sha256', $document->getId() . $signatory->getId() . 'secret_key');
    }

    public function getDocumentFile(Document $document): string
    {
        $filePath = $document->getFilePath();
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException('File not found');
        }
        return $filePath;
    }
} 