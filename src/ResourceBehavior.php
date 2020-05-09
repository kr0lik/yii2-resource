<?php
namespace kr0lik\resource;

use kr0lik\resource\Exception\ResourceAttributeException;
use kr0lik\resource\Exception\ResourceException;
use yii\base\Behavior;
use yii\base\InvalidArgumentException;
use yii\base\Model;
use yii\db\ActiveRecord;

/**
 * @property Model $owner
 */
class ResourceBehavior extends Behavior
{
    public $attributes = [];
    public $folder = 'upload/resource';
    public $tmpFolder = 'upload/temp';
    public $originalFileNameAttribute = 'original_file_name';

    /**
     * @var array<string, ResourceStrategy>
     */
    private $strategies = [];

    public function events()
    {
        return [
            Model::EVENT_BEFORE_VALIDATE => '_validateResource',
            ActiveRecord::EVENT_BEFORE_INSERT => '_saveResource',
            ActiveRecord::EVENT_BEFORE_UPDATE => '_saveResource',
            ActiveRecord::EVENT_AFTER_UPDATE => '_clearResource',
            ActiveRecord::EVENT_AFTER_DELETE => '_deleteResource',
        ];
    }

    public function _validateResource($event): bool
    {
        $status = true;

        /** @var ResourceStrategy $strategy */
        foreach ($this->getStrategies() as $attribute => $strategy) {
            try {
                $strategy->uploadResource();
            } catch (ResourceException $exception) {
                $this->owner->addError($attribute, $exception->getMessage());
                $status = false;
            }
        }

        return $status;
    }

    public function _deleteResource($event): bool
    {
        $status = true;

        /** @var ResourceStrategy $strategy */
        foreach ($this->getStrategies() as $attrinute => $strategy) {
            try {
                $strategy->deleteResource();
            } catch (ResourceException $exception) {
                $this->owner->addError($attribute, $exception->getMessage());
                $status = false;
            }
        }

        return $status;
    }

    public function _saveResource($event): bool
    {
        $status = true;

        /** @var ResourceStrategy $strategy */
        foreach ($this->getStrategies() as $attrinute => $strategy) {
            try {
                $strategy->saveResource();
            } catch (ResourceException $exception) {
                $this->owner->addError($attribute, $exception->getMessage());
                $status = false;
            }
        }

        return $status;
    }
    
    public function _clearResource($event): bool
    {
        $status = true;

        /** @var ResourceStrategy $strategy */
        foreach ($this->getStrategies() as $strategy) {
            try {
                $strategy->deleteOldResource();
            } catch (ResourceException $exception) {
                $this->owner->addError($attribute, $exception->getMessage());
                $status = false;
            }
        }

        return $status;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getResource(string $attribute, bool $absolute = false): string
    {
        if (array_key_exists($attribute, $this->getStrategies())) {
            /** @var ResourceStrategy $strategy */
            $strategy = $this->getStrategies()[$attribute];
            return $strategy->getResourcePath($absolute);
        }

        throw new InvalidArgumentException($attribute);
    }

    /**
     * @return array<string, ResourceStrategy>
     */
    private function getStrategies(): array
    {
        if ([] === $this->strategies) {
            foreach ($this->attributes as $attribute) {
                $this->strategies[$attribute] = new ResourceStrategy(
                    $this->owner,
                    $attribute,
                    $this->folder,
                    $this->tmpFolder,
                    $this->originalFileNameAttribute
                );
            }
        }

        return $this->strategies;
    }
}
