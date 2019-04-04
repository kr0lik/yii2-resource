# yii2-resource
Behaviour for resource store for Yii2

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require --prefer-dist kr0lik/yii2-resource "*"
```

or add

```
"kr0lik/yii2-resource": "*"
```

to the require section of your `composer.json` file.

Usage
-----

Add to params 'UploadTempFolder' => 'relative/path/to/temp/folder'

Add \kr0lik\recource\ResourceBehavior to your ActiveRecord

```php
use yii\db\ActiveRecord;
use kr0lik\recource\ResourceBehavior;

class YourModel extends ActiveRecord
{
    ...
    public function behaviors()
    {
        return [
            'resource' => [
                'class' => ResourceBehavior::class,
                'attributes' => ['file'],
                'folder' => 'path/to/store/file/folder',
                'tmpFolder' => 'path/to/temp/file/folder'
            ]
        ];
    }
    ...
}
```
