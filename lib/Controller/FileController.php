<?php
namespace OCA\FlattenPDF\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\IRequest;
use OCP\Files\IRootFolder;
use OCP\AppFramework\Http\JSONResponse;
use Psr\Log\LoggerInterface;

class FileController extends Controller {
    private $rootFolder;
    private $logger;

    public function __construct(string $appName, IRequest $request, IRootFolder $rootFolder, LoggerInterface $logger) {
        parent::__construct($appName, $request);
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function flatten(string $path): JSONResponse {
        $this->logger->debug("FlattenPDF: Starting flatten for {path}", ['app' => 'flattenpdf', 'path' => $path]);
        try {
            // Get user folder
            $userId = \OC::$server->getUserSession()->getUser()->getUID();
            $this->logger->debug("FlattenPDF: User ID: {userId}", ['app' => 'flattenpdf', 'userId' => $userId]);
            $userFolder = $this->rootFolder->getUserFolder($userId);
            
            // Normalize path (remove leading slash if present)
            $path = ltrim($path, '/');
            $this->logger->debug("FlattenPDF: Normalized path: {path}", ['app' => 'flattenpdf', 'path' => $path]);
            
            // Get the file
            $file = $userFolder->get($path);
            if (!$file->isReadable() || $file->getMimeType() !== 'application/pdf') {
                $this->logger->error("FlattenPDF: Invalid or inaccessible PDF: {path}", ['app' => 'flattenpdf', 'path' => $path]);
                return new JSONResponse(['error' => 'Invalid or inaccessible PDF'], 400);
            }

            // Get absolute server path
            $inputPath = $file->getStorage()->getLocalFile($file->getInternalPath());
            $this->logger->debug("FlattenPDF: Absolute input path: {inputPath}", ['app' => 'flattenpdf', 'inputPath' => $inputPath]);

            // Generate output path
            $outputPath = str_replace('.pdf', '_flat.pdf', $path);
            $outputFullPath = str_replace('.pdf', '_flat.pdf', $inputPath);
            $this->logger->debug("FlattenPDF: Output path: {outputFullPath}", ['app' => 'flattenpdf', 'outputFullPath' => $outputFullPath]);

            // Run pdftk
            $command = "pdftk " . escapeshellarg($inputPath) . " output " . escapeshellarg($outputFullPath) . " flatten 2>&1";
            exec($command, $output, $returnCode);
            $this->logger->debug("FlattenPDF: pdftk command: {command}, Return code: {returnCode}", ['app' => 'flattenpdf', 'command' => $command, 'returnCode' => $returnCode]);

            if ($returnCode !== 0) {
                $this->logger->error("FlattenPDF: pdftk failed: {output}", ['app' => 'flattenpdf', 'output' => implode("\n", $output)]);
                return new JSONResponse(['error' => 'Failed to flatten PDF: ' . implode("\n", $output)], 500);
            }

            // Register new file in cache
            $userFolder->newFile($outputPath);
            $this->logger->debug("FlattenPDF: Flattened PDF saved: {outputPath}", ['app' => 'flattenpdf', 'outputPath' => $outputPath]);

            return new JSONResponse(['success' => true, 'path' => $outputPath]);
        } catch (\Exception $e) {
            $this->logger->error("FlattenPDF: Error: {message}", ['app' => 'flattenpdf', 'message' => $e->getMessage()]);
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
}
