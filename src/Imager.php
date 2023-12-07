<?php
/**
 * Imager plugin for Craft CMS 3.x
 *
 * Image transforms gone wild
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2018 André Elvan
 */

namespace aelvan\imager;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\ReplaceAssetEvent;
use craft\helpers\FileHelper;
use craft\helpers\Image;
use craft\models\AssetTransform;
use craft\services\Assets;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;

use yii\base\Event;

use aelvan\imager\models\ConfigModel;
use aelvan\imager\models\TransformedImageInterface;
use aelvan\imager\elementactions\ClearTransformsElementAction;
use aelvan\imager\elementactions\ImgixPurgeElementAction;
use aelvan\imager\exceptions\ImagerException;
use aelvan\imager\models\Settings;
use aelvan\imager\services\PlaceholderService;
use aelvan\imager\services\ImagerService;
use aelvan\imager\services\ImagerColorService;
use aelvan\imager\services\ImgixService;
use aelvan\imager\variables\ImagerVariable;
use aelvan\imager\twigextensions\ImagerTwigExtension;
use aelvan\imager\helpers\ImagerHelpers;

use aelvan\imager\effects\BlurEffect;
use aelvan\imager\effects\ClutEffect;
use aelvan\imager\effects\ColorBlendEffect;
use aelvan\imager\effects\ColorizeEffect;
use aelvan\imager\effects\ContrastEffect;
use aelvan\imager\effects\ContrastStretchEffect;
use aelvan\imager\effects\GammaEffect;
use aelvan\imager\effects\GreyscaleEffect;
use aelvan\imager\effects\LevelsEffect;
use aelvan\imager\effects\ModulateEffect;
use aelvan\imager\effects\NegativeEffect;
use aelvan\imager\effects\NormalizeEffect;
use aelvan\imager\effects\PosterizeEffect;
use aelvan\imager\effects\QuantizeEffect;
use aelvan\imager\effects\SepiaEffect;
use aelvan\imager\effects\SharpenEffect;
use aelvan\imager\effects\TintEffect;
use aelvan\imager\effects\UnsharpMaskEffect;

use aelvan\imager\optimizers\GifsicleOptimizer;
use aelvan\imager\optimizers\ImageoptimOptimizer;
use aelvan\imager\optimizers\JpegoptimOptimizer;
use aelvan\imager\optimizers\JpegtranOptimizer;
use aelvan\imager\optimizers\KrakenOptimizer;
use aelvan\imager\optimizers\MozjpegOptimizer;
use aelvan\imager\optimizers\OptipngOptimizer;
use aelvan\imager\optimizers\PngquantOptimizer;
use aelvan\imager\optimizers\TinypngOptimizer;

use aelvan\imager\externalstorage\AwsStorage;
use aelvan\imager\externalstorage\GcsStorage;


/**
 * Class Imager
 *
 * @author    André Elvan
 * @package   Imager
 * @since     2.0.0
 *
 * @property  ImagerService $imager
 * @property  ImagerColorService $color
 * @property  PlaceholderService $placeholder
 * @property  ImgixService $imgix
 */
class Imager extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Imager::$plugin
     *
     * @var Imager
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        // Register services
        $this->setComponents([
            'imager' => ImagerService::class,
            'placeholder' => PlaceholderService::class,
            'color' => ImagerColorService::class,
            'imgix' => ImgixService::class,
        ]);

        // Add our Twig extensions
        Craft::$app->view->registerTwigExtension(new ImagerTwigExtension());

        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();

        // Register our variables
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('imager', ImagerVariable::class);
            }
        );

        // Adds Imager paths to the list of things the Clear Caches tool can delete
        Event::on(ClearCaches::class, ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function (RegisterCacheOptionsEvent $event) {
                $event->options[] = [
                    'key' => 'imager-transform-cache',
                    'label' => Craft::t('imager', 'Imager image transform cache'),
                    'action' => FileHelper::normalizePath(ImagerService::getConfig()->imagerSystemPath)
                ];
                $event->options[] = [
                    'key' => 'imager-remote-images-cache',
                    'label' => Craft::t('imager', 'Imager remote images cache'),
                    'action' => FileHelper::normalizePath(Craft::$app->getPath()->getRuntimePath() . '/imager/')
                ];
            }
        );

        // Register element action to assets for clearing transforms
        Event::on(Asset::class, Element::EVENT_REGISTER_ACTIONS,
            function (RegisterElementActionsEvent $event) use ($config) {
                $event->actions[] = ClearTransformsElementAction::class;
                // If Imgix purging is possible, add element action for purging – unless the element action is disabled
                if ($config->imgixEnablePurgeElementAction && ImgixService::getCanPurge()) {
                    $event->actions[] = ImgixPurgeElementAction::class;
                }
            }
        );

        // Event listener for clearing caches when an asset is replaced
        Event::on(Assets::class, Assets::EVENT_AFTER_REPLACE_ASSET,
            function (ReplaceAssetEvent $event) use ($config) {
                if ($event->asset) {
                    self::$plugin->imager->removeTransformsForAsset($event->asset);
                    // If Imgix purging is possible, do that too
                    if ($config->imgixEnableAutoPurging && ImgixService::getCanPurge()) {
                        self::$plugin->imgix->purgeAssetFromImgix($event->asset);
                    }
                }
            }
        );

        // Event listener for overriding Craft's internal transform functionality
        Event::on(Assets::class, \craft\elements\Asset::EVENT_DEFINE_URL,
            function (\craft\events\DefineAssetUrlEvent $event) {
                $config = ImagerService::getConfig();

                if ($config->useForNativeTransforms && $event->asset !== null && $event->transform !== null && $event->asset->kind === 'image' && \in_array(strtolower($event->asset->getExtension()), Image::webSafeFormats(), true)) {
                    try {
                        $transform = $event->transform;

                        // Transform is an AssetTransform
                        if ($transform instanceof AssetTransform) {
                            $transform = ImagerHelpers::normalizeAssetTransformToObject($transform);
                        }

                        // Transform is a named asset transform
                        if (is_string($transform)) {
                            $assetTransform = Craft::$app->getAssetTransforms()->getTransformByHandle($transform);

                            if ($assetTransform) {
                                $transform = ImagerHelpers::normalizeAssetTransformToObject($assetTransform);
                            } else {
                                Craft::error('Unknown asset transform handle supplied to native transform', __METHOD__);
                                $transform = [];
                            }
                        }

                        if (is_array($transform)) {
                            $transformedImage = self::$plugin->imager->transformImage($event->asset, $transform);

                            if ($transformedImage !== null) {
                                $event->url = $transformedImage->getUrl();
                            }
                        }
                    } catch (ImagerException $e) {
                        return null;
                    }
                }
            }
        );

        // Event listener for overriding Craft's internal thumb url
        Event::on(Assets::class, Assets::EVENT_DEFINE_THUMB_URL,
            function (\craft\events\DefineAssetThumbUrlEvent $event) {
                $config = ImagerService::getConfig();

                if ($config->useForCpThumbs && $event->asset !== null && $event->asset->kind === 'image' && \in_array(strtolower($event->asset->getExtension()), Image::webSafeFormats(), true)) {
                    try {
                        /** @var TransformedImageInterface $transformedImage */
                        $transformedImage = self::$plugin->imager->transformImage($event->asset, ['width' => $event->width, 'height' => $event->height, 'mode' => 'fit']);

                        if ($transformedImage !== null) {
                            $event->url = $transformedImage->getUrl();
                        }
                    } catch (ImagerException $e) {
                        // just ignore
                    }
                }
            }
        );

        // Register built-in effects
        $this->registerEffects();

        // Register built-in optimizers
        $this->registerOptimizers();

        // Register built-in external storage options
        $this->registerExternalStorages();
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    /**
     * Register built-in effects
     */
    private function registerEffects()
    {
        // Both for GD and Imagick
        ImagerService::registerEffect('grayscale', GreyscaleEffect::class);
        ImagerService::registerEffect('greyscale', GreyscaleEffect::class);
        ImagerService::registerEffect('negative', NegativeEffect::class);
        ImagerService::registerEffect('blur', BlurEffect::class);
        ImagerService::registerEffect('sharpen', SharpenEffect::class);
        ImagerService::registerEffect('gamma', GammaEffect::class);
        ImagerService::registerEffect('colorize', ColorizeEffect::class);

        // Imagick only
        ImagerService::registerEffect('colorblend', ColorBlendEffect::class);
        ImagerService::registerEffect('sepia', SepiaEffect::class);
        ImagerService::registerEffect('tint', TintEffect::class);
        ImagerService::registerEffect('contrast', ContrastEffect::class);
        ImagerService::registerEffect('modulate', ModulateEffect::class);
        ImagerService::registerEffect('normalize', NormalizeEffect::class);
        ImagerService::registerEffect('contraststretch', ContrastStretchEffect::class);
        ImagerService::registerEffect('posterize', PosterizeEffect::class);
        ImagerService::registerEffect('unsharpmask', UnsharpMaskEffect::class);
        ImagerService::registerEffect('clut', ClutEffect::class);
        ImagerService::registerEffect('levels', LevelsEffect::class);
        ImagerService::registerEffect('quantize', QuantizeEffect::class);
    }

    /**
     * Register built-in optimizers
     */
    private function registerOptimizers()
    {
        ImagerService::registerOptimizer('jpegoptim', JpegoptimOptimizer::class);
        ImagerService::registerOptimizer('jpegtran', JpegtranOptimizer::class);
        ImagerService::registerOptimizer('mozjpeg', MozjpegOptimizer::class);
        ImagerService::registerOptimizer('optipng', OptipngOptimizer::class);
        ImagerService::registerOptimizer('pngquant', PngquantOptimizer::class);
        ImagerService::registerOptimizer('gifsicle', GifsicleOptimizer::class);
        ImagerService::registerOptimizer('tinypng', TinypngOptimizer::class);
        ImagerService::registerOptimizer('kraken', KrakenOptimizer::class);
        ImagerService::registerOptimizer('imageoptim', ImageoptimOptimizer::class);
    }

    /**
     * Register built-in external storage options
     */
    private function registerExternalStorages()
    {
        ImagerService::registerExternalStorage('aws', AwsStorage::class);
        ImagerService::registerExternalStorage('gcs', GcsStorage::class);
    }

}
