<?php

namespace aelvan\imager\jobs;

use aelvan\imager\exceptions\ImagerException;
use Craft;

use craft\queue\BaseJob;

use aelvan\imager\models\ConfigModel;
use aelvan\imager\services\ImagerService;

class OptimizeJob extends BaseJob
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $optimizer = '';
    
    /**
     * @var array
     */
    public $optimizerSettings = [];
    
    /**
     * @var string
     */
    public $filePath = '';


    // Public Methods
    // =========================================================================

    /**
     * @param \craft\queue\QueueInterface|\yii\queue\Queue $queue
     * @throws ImagerException
     */
    public function execute($queue): void
    {
        if (isset(ImagerService::$optimizers[$this->optimizer])) {
            ImagerService::$optimizers[$this->optimizer]::optimize($this->filePath, $this->optimizerSettings);
            
            // Clear stat cache to make sure old file size is not cached
            clearstatcache(true, $this->filePath);
            
            /** @var ConfigModel $settings */
            $config = ImagerService::getConfig();
    
            if (empty($config->storages)) {
                return;
            }
    
            $uri = str_replace($config->imagerSystemPath, '', $this->filePath);
    
            foreach ($config->storages as $storage) {
                if (isset(ImagerService::$storage[$storage])) {
                    $storageSettings = $config->storageConfig[$storage] ?? null;
    
                    if ($storageSettings) {
                        $result = ImagerService::$storage[$storage]::upload($this->filePath, $uri, true, $storageSettings);
                    
                        if (!$result) {
                            throw new ImagerException('Upload to storage "' . $storage . '" failed for job "' . $this->getDescription() . '"');
                        }
                    } else {
                        Craft::error('Could not find settings for storage "'.$storage.'"', __METHOD__);
                    }
                } else {
                    Craft::error('Could not find a registered storage with handle "'.$storage.'"', __METHOD__);
                }
            }
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * Returns a default description for [[getDescription()]], if [[description]] isn’t set.
     *
     * @return string The default task description
     */
    protected function defaultDescription(): string
    {
        return Craft::t('imager', 'Optimizing images');
    }
}
