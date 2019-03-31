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
            ActiveRecord::EVENT_BEFORE_VALIDATE => '_validate',
            ActiveRecord::EVENT_BEFORE_INSERT => '_save',
            ActiveRecord::EVENT_BEFORE_UPDATE => '_save',
            ActiveRecord::EVENT_AFTER_UPDATE => '_clear',
            ActiveRecord::EVENT_AFTER_DELETE => '_delete'
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

    public function _validate($event)
    {
        $status = true;

        foreach ($this->getStrategies() as $strategy) {
            if ($strategy->uploadResource() === false) {
                $status = false;
            }
        }

        return $status;
    }

    public function _delete($event)
    {
        $status = true;

        foreach ($this->getStrategies() as $strategy) {
            if ($strategy->deleteResource() === false) {
                $status = false;
            }
        }

        return $status;
    }

    public function _save($event)
    {
        $status = true;

        foreach ($this->getStrategies() as $strategy) {
            if ($strategy->saveResource() === false) {
                $status = false;
            }
        }

        return $status;
    }
    
    public function _clear($event)
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
