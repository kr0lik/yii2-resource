<?php
namespace kr0lik\recource;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;

class ResourceBehavior extends Behavior
{
    public $attributes = [];
    public $folder = 'image';

    protected $strategies = [];

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_VALIDATE => '_validateResource',
            ActiveRecord::EVENT_BEFORE_INSERT => '_saveResource',
            ActiveRecord::EVENT_BEFORE_UPDATE => '_saveResource',
            ActiveRecord::EVENT_AFTER_UPDATE => '_clearResource',
            ActiveRecord::EVENT_AFTER_DELETE => '_deleteResource'
        ];
    }

    protected function getStrategies()
    {
        if (! $this->strategies) {
            foreach ($this->attributes as $attribute) {
                $this->strategies[$attribute] = new ResourceStrategy(
                    $this->owner,
                    $attribute,
                    $this->folder,
                    Yii::$app->params['UploadTempFolder']
                );
            }
        }

        return $this->strategies;
    }

    public function _validateResource($event)
    {
        $status = true;

        foreach ($this->getStrategies() as $strategy) {
            if ($strategy->uploadResource() === false) {
                $status = false;
            }
        }

        return $status;
    }

    public function _deleteResource($event)
    {
        $status = true;

        foreach ($this->getStrategies() as $strategy) {
            if ($strategy->deleteResource() === false) {
                $status = false;
            }
        }

        return $status;
    }

    public function _saveResource($event)
    {
        $status = true;

        foreach ($this->getStrategies() as $strategy) {
            if ($strategy->saveResource() === false) {
                $status = false;
            }
        }

        return $status;
    }
    
    public function _clearResource($event)
    {
        $status = true;

        foreach ($this->getStrategies() as $strategy) {
            if ($strategy->deleteOldResource() === false) {
                $status = false;
            }
        }

        return $status;
    }


    public function getResource(string $attribute, bool $absolute = false)
    {
        return $this->getStrategies()[$attribute]->getResourcePath($absolute);
    }
}
