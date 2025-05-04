<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Repository\DocumentVersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\SluggerInterface;

class DocumentVersionService
{
    public function __construct(
        private DocumentVersionRepository $versionRepository,
        private EntityManagerInterface $entityManager,
        private string $uploadDirectory,
        private string $versionedUploadDirectory,
        private SluggerInterface $slugger,
        private LoggerInterface $logger
    ) {
        // Ensure the versions directory exists
        $filesystem = new Filesystem();
        if (!is_dir($this->versionedUploadDirectory)) {
            $filesystem->mkdir($this->versionedUploadDirectory);
        }
    }

    public function createVersion(Document $document, ?string $changeDescription = null): DocumentVersion
    {
        // Get the latest version number
        $latestVersion = $this->versionRepository->findLatestVersion($document);
        $versionNumber = $latestVersion ? $this->incrementVersion($latestVersion->getVersionNumber()) : '1.0';

        // Create new version
        $version = new DocumentVersion();
        $version->setDocument($document);
        $version->setVersionNumber($versionNumber);
        $version->setChangeDescription($changeDescription);
        $version->setCreatedBy($document->getCreatedBy());

        // Copy the file to versioned storage
        $originalFilePath = $document->getFilePath();
        $versionedFilePath = $this->createVersionedFilePath($document, $versionNumber);
        
        $filesystem = new Filesystem();
        
        // Log the paths for debugging
        $this->logger->info('Creating version', [
            'originalFilePath' => $originalFilePath,
            'versionedFilePath' => $versionedFilePath,
            'uploadDirectory' => $this->uploadDirectory,
            'versionedUploadDirectory' => $this->versionedUploadDirectory
        ]);

        // Ensure the source file exists
        $sourcePath = $this->uploadDirectory . '/' . basename($originalFilePath);
        if (!file_exists($sourcePath)) {
            $this->logger->error('Source file not found', ['path' => $sourcePath]);
            throw new \RuntimeException(sprintf('Source file "%s" does not exist', $sourcePath));
        }

        // Copy to versioned location
        $targetPath = $this->versionedUploadDirectory . '/' . $versionedFilePath;
        $filesystem->copy($sourcePath, $targetPath);

        // Verify the copy was successful
        if (!file_exists($targetPath)) {
            $this->logger->error('Failed to create versioned file', ['path' => $targetPath]);
            throw new \RuntimeException(sprintf('Failed to create versioned file at "%s"', $targetPath));
        }

        $version->setFilePath($versionedFilePath);

        $this->entityManager->persist($version);
        $this->entityManager->flush();

        return $version;
    }

    public function restoreVersion(Document $document, DocumentVersion $version): void
    {
        // Copy the versioned file back to the main document
        $filesystem = new Filesystem();
        
        $sourcePath = $this->versionedUploadDirectory . '/' . $version->getFilePath();
        if (!file_exists($sourcePath)) {
            $this->logger->error('Version file not found', ['path' => $sourcePath]);
            throw new \RuntimeException(sprintf('Version file "%s" does not exist', $sourcePath));
        }

        $targetPath = $this->uploadDirectory . '/' . basename($document->getFilePath());
        $filesystem->copy($sourcePath, $targetPath, true);

        // Update document metadata
        $document->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function incrementVersion(string $versionNumber): string
    {
        $parts = explode('.', $versionNumber);
        $major = (int)$parts[0];
        $minor = isset($parts[1]) ? (int)$parts[1] : 0;
        
        return $major . '.' . ($minor + 1);
    }

    private function createVersionedFilePath(Document $document, string $versionNumber): string
    {
        $originalFilename = pathinfo($document->getFilePath(), PATHINFO_FILENAME);
        $extension = pathinfo($document->getFilePath(), PATHINFO_EXTENSION);
        
        $safeFilename = $this->slugger->slug($originalFilename);
        return $safeFilename . '-v' . $versionNumber . '.' . $extension;
    }
} 