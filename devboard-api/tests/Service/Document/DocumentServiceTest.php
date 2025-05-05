<?php

namespace App\Tests\Service\Document;

use App\Service\Document\DocumentService;
use App\Repository\DocumentRepository;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class DocumentServiceTest extends TestCase
{
    public function testGetDocumentsReturnsRepositoryResult()
    {
        $expected = [['id' => 1, 'title' => 'Test Document']];

        $documentRepository = $this->createMock(DocumentRepository::class);
        $documentRepository->expects($this->once())
            ->method('findByFilters')
            ->with('draft', true, '1')
            ->willReturn($expected);

        $service = new DocumentService(
            $documentRepository,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(AuditLogRepository::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(Environment::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(SluggerInterface::class),
            $this->createMock(LoggerInterface::class),
            '/tmp'
        );

        $result = $service->getDocuments('draft', true, '1');
        $this->assertSame($expected, $result);
    }

    public function testCreateDocumentThrowsOnWrongUserType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $service = new DocumentService(
            $this->createMock(DocumentRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(AuditLogRepository::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(Environment::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(SluggerInterface::class),
            $this->createMock(LoggerInterface::class),
            '/tmp'
        );
        $service->createDocument('{}', $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class));
    }

    public function testUpdateDocumentCallsFlushAndReturnsDocument()
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('deserialize');
        $service = new DocumentService(
            $this->createMock(DocumentRepository::class),
            $entityManager,
            $serializer,
            $this->createMock(AuditLogRepository::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(Environment::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(SluggerInterface::class),
            $this->createMock(LoggerInterface::class),
            '/tmp'
        );
        $doc = $this->createMock(\App\Entity\Document::class);
        $result = $service->updateDocument($doc, '{}');
        $this->assertSame($doc, $result);
    }

    public function testDeleteDocumentCallsRemoveAndFlush()
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove');
        $entityManager->expects($this->once())->method('flush');
        $service = new DocumentService(
            $this->createMock(DocumentRepository::class),
            $entityManager,
            $this->createMock(SerializerInterface::class),
            $this->createMock(AuditLogRepository::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(Environment::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(SluggerInterface::class),
            $this->createMock(LoggerInterface::class),
            '/tmp'
        );
        $doc = $this->createMock(\App\Entity\Document::class);
        $service->deleteDocument($doc);
    }

    public function testGetAuditLogsReturnsRepositoryResult()
    {
        $expected = [['id' => 1, 'action' => 'created']];
        $auditLogRepository = $this->createMock(AuditLogRepository::class);
        $auditLogRepository->expects($this->once())
            ->method('findBy')
            ->willReturn($expected);
        $service = new DocumentService(
            $this->createMock(DocumentRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(SerializerInterface::class),
            $auditLogRepository,
            $this->createMock(MailerInterface::class),
            $this->createMock(Environment::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(SluggerInterface::class),
            $this->createMock(LoggerInterface::class),
            '/tmp'
        );
        $doc = $this->createMock(\App\Entity\Document::class);
        $result = $service->getAuditLogs($doc);
        $this->assertSame($expected, $result);
    }

    public function testCreateDocumentSetsFilePath()
    {
        $user = $this->createMock(\App\Entity\WpUser::class);

        $document = $this->createMock(\App\Entity\Document::class);
        $document->expects($this->once())->method('setCreatedBy')->with($user);
        $document->expects($this->once())->method('getFilePath')->willReturn('testfile.pdf');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('deserialize')
            ->willReturn($document);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($document);
        $entityManager->expects($this->once())->method('flush');

        $service = new \App\Service\Document\DocumentService(
            $this->createMock(DocumentRepository::class),
            $entityManager,
            $serializer,
            $this->createMock(AuditLogRepository::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(Environment::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(SluggerInterface::class),
            $this->createMock(LoggerInterface::class),
            '/tmp'
        );

        $result = $service->createDocument('{"filePath":"testfile.pdf"}', $user);
        $this->assertSame('testfile.pdf', $result->getFilePath());
    }
} 