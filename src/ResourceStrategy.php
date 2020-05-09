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
    const HASH_SPLIT_LENGHT = 2;

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
        $this->relativeTempFolder = $relativeTempFolder;
        $this->originalFileNameAttribute = $originalFileNameAttribute;
    }

    public function getResourcePath(bool $absolute = false): string
    {
        $resourceName = $this->getResourceName();

        if ($this->isTempResource($resourceName)) {
            $path = $this->getRelativeResourceTempPath($resourceName);
        } else {
            $path = $this->getRelativeResourcePath($resourceName);
        }

        if ($absolute) {
            return Yii::getAlias('@webroot') . $path;
        }

        return $path;
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

            $tempFolder = $this->getAbsoluteTempFolder();
            if (! is_dir($tempFolder)) {
                FileHelper::createDirectory($tempFolder);
            }

            $savePath = $this->getAbsoluteResourceTempPath($newFileName);
            if ($resource->saveAs($savePath)) {
                chmod($savePath, 0775);
                $this->model->{$this->attribute} = $this->getRelativeResourceTempPath($newFileName);
                if (null !== $this->originalFileNameAttribute) {
                    $this->model->{$this->originalFileNameAttribute} = $resource->name;
                }
            } else {
                throw new ResourceSaveException($savePath);
            }
        } elseif ($this->isTempResource($resource)) {
            $fullPath = $this->getAbsoluteResourceTempPath($resource);
            if (!file_exists($fullPath)) {
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

            $newDir = $this->getAbsoluteResourceFolder($newFileName);
            if (!is_dir($newDir)) {
                FileHelper::createDirectory($newDir);
            }

            $newFilePath = $this->getAbsoluteResourcePath($newFileName);
            if (file_exists($newFilePath)) {
                $existsFiles = glob($newDir . pathinfo($newFileName, PATHINFO_FILENAME) . '_*.' . $extension);
                $newFileName = pathinfo($newFileName, PATHINFO_FILENAME) . '_' . count($existsFiles) . '.' . $extension;
            }

            if (copy($this->getAbsoluteResourceTempPath($resourceName), $newFilePath)) {
                $this->oldResource = $resourceName;
                $this->model->{$this->attribute} = $newFileName;
            } else {
                throw new ResourceSaveException($newFilePath);
            }
        } else {
            $resourcePath = $this->getAbsoluteResourcePath($resourceName);
            if (!file_exists($resourcePath)) {
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

        return $this->model->{$this->attribute};
    }

    private function isTempResource(string $resourceName): bool
    {
        return false !== strpos($resourceName, trim($this->getRelativeTempFolder(), DIRECTORY_SEPARATOR));
    }

    private function generateHash(): string
    {
        return md5(uniqid('', true));
    }

    private function getPathFromHash(string $hash): string
    {
        $hash = str_replace(DIRECTORY_SEPARATOR, '', $hash);
        $hash = substr($hash, 0, self::HASH_LENGTH);
        return DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, str_split($hash, self::HASH_SPLIT_LENGHT)) . DIRECTORY_SEPARATOR;
    }

    private function getRelativeResourceFolder(string $resourceName): string
    {
        $resourcefolder = DIRECTORY_SEPARATOR . trim($this->relativeResourceFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return $resourcefolder . ltrim($this->getPathFromHash($resourceName), DIRECTORY_SEPARATOR);
    }

    private function getRelativeTempFolder(): string
    {
        return DIRECTORY_SEPARATOR . trim($this->relativeTempFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function getRelativeResourcePath(string $resourceName): string
    {
        return  $this->getRelativeResourceFolder($resourceName) . $resourceName;
    }

    private function getRelativeResourceTempPath(string $resourceName): string
    {
        $tempPath = $this->getRelativeTempFolder();

        return $tempPath . trim(str_replace($tempPath, '', $resourceName), DIRECTORY_SEPARATOR);
    }


    private function getAbsoluteTempFolder(): string
    {
        return Yii::getAlias('@webroot') . $this->getRelativeTempFolder();
    }

    private function getAbsoluteResourceFolder(string $resourceName): string
    {
        return Yii::getAlias('@webroot') . $this->getRelativeResourceFolder($resourceName);
    }

    private function getAbsoluteResourceTempPath(string $resourceName): string
    {
        return Yii::getAlias('@webroot') . $this->getRelativeResourceTempPath($resourceName);
    }

    private function getAbsoluteResourcePath(string $resourceName): string
    {
        return  Yii::getAlias('@webroot') . $this->getRelativeResourcePath($resourceName);
    }

    /**
     * @throws ResourceMoveToTempException
     */
    private function moveResourceToTemp(string $oldResourceName): void
    {
        $tempFolder = $this->getAbsoluteTempFolder();
        if (! is_dir($tempFolder)) {
            FileHelper::createDirectory($tempFolder);
        }

        $oldFilePath = $this->getAbsoluteResourcePath($oldResourceName);
        if (file_exists($oldFilePath)) {
            $newTempPath = $this->getAbsoluteResourceTempPath($oldResourceName);
            if (file_exists($newTempPath)) unlink($newTempPath);
            if (!@rename($oldFilePath, $newTempPath)) {
                throw new ResourceMoveToTempException($oldFilePath);
            }
        }

        // For images with filter in name
        $filteredImages = glob($this->getAbsoluteResourceFolder($oldResourceName) . pathinfo($oldResourceName, PATHINFO_FILENAME) . '.*.' . pathinfo($oldResourceName, PATHINFO_EXTENSION));
        foreach ($filteredImages as $image) {
            $newTempPath = $this->getAbsoluteResourceTempPath($image);
            if (file_exists($newTempPath)) unlink($newTempPath);
            if (!@rename($image, $newTempPath)) {
                throw new ResourceMoveToTempException($image);
            }
        }
    }
}
