<?php
namespace kr0lik\resource;

use kr0lik\resource\Exception\{ResourceExistsException, ResourceMoveToTempException, ResourceSaveException};
use Yii;
use yii\base\Model;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class ResourceStrategy
{
    const HASH_LENGTH = 6;

    private $model;
    private $attribute;
    private $relativeResourceFolder;
    private $relativeTempFolder;
    private $originalFileNameAttribute;

    private $oldResource;

    public function __construct(Model $model, string $attribute, string $relativeResourceFolder, string $relativeTempFolder, ?string $originalFileNameAttribute)
    {
        $this->model = $model;
        $this->attribute = $attribute;
        $this->relativeResourceFolder = $relativeResourceFolder;
        $this->tempFolder = $relativeTempFolder;
        $this->originalFileNameAttribute = $originalFileNameAttribute;
    }

    public function getResourcePath(bool $absolute = false): string
    {
        $resourceName = $this->getResourceName();

        if ($this->isTempResource($resourceName)) {
            return $this->getResourceTempPath($resourceName, $absolute);
        } else {
            return $this->getResourceDestinationPath($resourceName, $absolute);
        }
    }

    /**
     * @throws ResourceExistsException
     * @throws ResourceSaveException
     */
    public function uploadResource(): void
    {
        $resource = $this->model->{$this->attribute};

        if ($resource instanceof UploadedFile) {
            $extension = $resource->getExtension();
            $newFileName = $this->generateHash() . '.' . $extension;

            $tempFolder = $this->getTempPath(true);
            if (! is_dir($tempFolder)) {
                FileHelper::createDirectory($tempFolder);
            }

            $savePath = $this->getResourceTempPath($newFileName, true);
            if ($resource->saveAs($savePath)) {
                chmod($savePath, 0775);
                $this->model->{$this->attribute} = $this->getResourceTempPath($newFileName);
                if (null !== $this->originalFileNameAttribute) {
                    $this->model->{$this->originalFileNameAttribute} = $file->name;
                }
            } else {
                throw new ResourceSaveException($savePath);
            }
        } elseif ($this->isTempResource($resource)) {
            $fullPath = $this->getResourcePath(true);
            if (! file_exists($fullPath)) {
                throw new ResourceExistsException($fullPath, 'Not exists temp resource.');
            }
        }
    }

    /**
     * @throws ResourceExistsException
     * @throws ResourceSaveException
     */
    public function saveResource(): void
    {
        $resourceName = $this->getResourceName();

        if ($this->isTempResource($resourceName)) {
            $extension = pathinfo($resourceName, PATHINFO_EXTENSION);
            $newFileName = $this->generateHash() . '.' . $extension;

            $newDir = $this->getDestinationPath($newFileName, true);
            if (!is_dir($newDir)) {
                FileHelper::createDirectory($newDir);
            }

            $newFilePath = $this->getResourceDestinationPath($newFileName, true);

            if (file_exists($newFilePath)) {
                $existsFiles = glob($newDir . pathinfo($newFileName, PATHINFO_FILENAME) . '_*.' . $extension);
                $newFileName = pathinfo($newFileName, PATHINFO_FILENAME) . '_' . count($existsFiles) . '.' . $extension;
            }

            if (copy($this->getResourcePath(true), $newFilePath)) {
                $this->oldResource = $this->model->{$this->attribute};
                $this->model->{$this->attribute} = $newFileName;
            } else {
                throw new ResourceSaveException($newFilePath);
            }
        } else {
            $resourcePath = $this->getResourceDestinationPath($resourceName, true);
            if (file_exists($resourcePath)) {
                throw new ResourceExistsException($resourcePath);
            }
        }
    }

    /**
     * @throws ResourceExistsException
     * @throws ResourceMoveToTempException
     * @throws ResourceSaveException
     */
    public function deleteResource(): void
    {
        $this->moveResourceToTemp($this->getResourceName());
    }

    /**
     * @throws ResourceMoveToTempException
     */
    public function deleteOldResource(): void
    {
        if ($this->oldResource) {
            $this->moveResourceToTemp($this->oldResource);
            $this->oldResource = null;
        }
        
        $model = $this->model;
        $oldAttributes = $model->getOldAttributes();
        $oldFile = isset($oldAttributes[$this->attribute]) && $oldAttributes[$this->attribute] ? $oldAttributes[$this->attribute] : null;

        if ($oldFile && $oldFile != $model->{$this->attribute}) {
            $this->moveResourceToTemp($oldFile);
        }
    }

    private function generateHash(): string
    {
        return md5(uniqid('', true));
    }

    private function getPathFromHash(string $hash): string
    {
        $hash = str_replace(DIRECTORY_SEPARATOR, '', $hash);
        $hash = substr($hash, 0, self::HASH_LENGTH);
        return DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, str_split($hash, 2)) . DIRECTORY_SEPARATOR;
    }

    private function getRelativeResourceFolder(): string
    {
        return DIRECTORY_SEPARATOR . trim($this->relativeResourceFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function getDestinationPath(string $hash, bool $absolute = false): string
    {
        $path = $this->getRelativeResourceFolder() . ltrim($this->getPathFromHash($hash), DIRECTORY_SEPARATOR);

        if (true === $absolute) {
            $path = Yii::getAlias('@webroot') . $path;
        }

        return $path;
    }

    private function getTempPath(bool $absolute = false): string
    {
        $path = DIRECTORY_SEPARATOR . trim($this->relativeTempFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (true === $absolute) {
            $path = Yii::getAlias('@webroot') . $path;
        }

        return $path;
    }

    /**
     * @throws ResourceExistsException
     * @throws ResourceSaveException
     */
    private function getResourceName(): string
    {
        $resourceName = $this->model->{$this->attribute};

        if ($resourceName instanceof UploadedFile) {
            $this->uploadResource();
        }

        return $resourceName;
    }

    private function isTempResource(string $resourceName): bool
    {
        return strpos($resourceName, trim($this->getTempPath(), DIRECTORY_SEPARATOR)) !== false;
    }

    protected function getResourceDestinationPath(string $resourceName, bool $absolute = false): string
    {
        return  $this->getDestinationPath($absolute) . $resourceName;
    }

    protected function getResourceTempPath(string $resourceName, bool $absolute = false): string
    {
        return  $this->getTempPath($absolute) . trim(str_replace($this->getTempPath(), '', $resourceName), DIRECTORY_SEPARATOR);
    }

    /**
     * @throws ResourceMoveToTempException
     */
    private function moveResourceToTemp(string $oldResourceName): void
    {
        $tempFolder = $this->getTempPath(true);
        if (! is_dir($tempFolder)) {
            FileHelper::createDirectory($tempFolder);
        }

        $oldFilePath = $this->getResourceDestinationPath($oldResourceName, true);
        if (file_exists($oldFilePath)) {
            $newTempPath = $this->getResourceTempPath($oldResourceName, true);
            if (file_exists($newTempPath)) unlink($newTempPath);
            if (!@rename($oldFilePath, $newTempPath)) {
                throw new ResourceMoveToTempException($oldFilePath);
            }
        }

        // For images with filter in name
        $filteredImages = glob($this->getDestinationPath($oldResourceName, true) . pathinfo($oldResourceName, PATHINFO_FILENAME) . '.*.' . pathinfo($oldResourceName, PATHINFO_EXTENSION));
        foreach ($filteredImages as $image) {
            $newTempPath = $this->getResourceTempPath($image, true);
            if (file_exists($newTempPath)) unlink($newTempPath);
            if (!@rename($image, $newTempPath)) {
                throw new ResourceMoveToTempException($image);
            }
        }
    }
}
