# Imager Changelog

### Added
- feat: Craft CMS 4.x compatibility work

## 2.4.0 - 2020-02-11

> {warning} This version of Imager is no longer actively maintained. [Imager X](https://plugins.craftcms.com/imager-x) is the successor of Imager 2.0, a commercial plugin with several new and awesome features.

### Changed
- Changed README to inform about [Imager X](https://plugins.craftcms.com/imager-x).  

### Fixed
- Fixed an issue where Imgix URLs with special characters were being encoded unnecessarily. 

## 2.3.1 - 2019-12-30
### Fixed
- Fixed an issue where realpath() was used unnecessarily and introduced a bug that would result in incorrect file paths when uploading to external storages (fixes #289, thanks @SanderVanLeeuwen).

## 2.3.0 - 2019-09-21

> {warning} As of Imager 2.3.0, the native focal point will automatically be used for transforms on Asset elements, _if no `position` is set in the transform_. If you were relying on the configuration setting `position` as the fallback value in previous versions, this could result in new transforms being created for some images, and you should consider adding your default value explicitly before upgrading. 

### Changed
- Imager will now automatically use native focal point for Asset elements, if no position is set by the transform. (fixes #270). 


## 2.2.0 - 2019-09-16
### Added
- Added missing `noop` implementation (fixes #260). 

### Changed
- Changed how watermark transform string is generated to avoid collisions due to keys in position (fixes #244). 
- AWS credentials can now be configured via IAM role, shared credentials file, or environment variables, when `accessKey` and `secretAccessKey` are left blank in the storage config settings (Thanks, @carlcs). 
- The `requestHeaders` and `storageType` storage config settings for AWS are now optional (Thanks, @carlcs).

### Fixed
- Fixed an issue where an error thrown while purging images on Imgix could result in assets not being replaced correctly and JS alerts IN CP. Purging is now silent, check logs for errors (fixes #261).
- Fixed issue where gif placeholder would not work if the image driver was left at `'auto'` in Craft's general config (fixes #195).
- Fixed incorrect calculation of image size in `ImgixTransformedImageModel` under very specific circumstances (fixes #238).
- Normalized path to `imagerSystemPath` returned from `Settings` model (Thanks, @andersaloof).
- Fixed directory separators in temp file path.

## 2.1.10 - 2019-04-13
### Fixed
- Fixed issues with using named transforms or `AssetTransform` models when `useForNativeTransforms` is `true` (fixes #237).
- Fixed incorrect use of config setting `imagerSystemPath` when clearing cache (fixes #236).
- Fixed docs for imgixConfig, domains are suppose to be strings.
- Fixed default value for `getExternalImageDimensions` in `ImgixSettings` model to correspond with the description in docs, and the example in the `Settings` model.

## 2.1.9 - 2019-04-04
### Fixed
- Fixed path encoding for AWS and GCS when using Windows (Thanks, @aaronwaldon).
- Fixed path encoding for Imgix when using Windows (Thanks, @aaronwaldon).
- Fixed error that would occur when using cwebp and the path had a space. (fixes #220).
- Optimize job now throws an exception if upload to external storage fails, so that it can be retried.
- AWS and GCS external storages now open files in binary mode just to be safe.

## 2.1.8.1 - 2019-04-03
### Added
- Added environment parsing to volume subfolder setting in Imgix path parsing (Thanks, @Tam).

## 2.1.8 - 2019-04-02
### Added
- Added support for addPath in Imgix config to make it easier to use several volumes with just one Imgix source and configuration.

## 2.1.7 - 2019-03-20
### Fixed
- Fixed regression error where settings that could have alias was not possible to override with template config overrides.

## 2.1.6 - 2019-03-19
### Fixed
- Fixes issue where spaces in asset folders broke URL encoding for Imgix.
- Fixes missing width and height when using Imgix and neither width or height was set in the transform (fixes #228).

## 2.1.5.1 - 2019-03-08
### Fixed
- Fixes issue where files with upper case extensions were not transformed with Imager when useForCpThumbs was activated. (thanks, @johandouma).

## 2.1.5 - 2019-02-21
### Fixed
- Fixes issue where files with upper case extensions were not transformed with Imager when useForNativeTransforms was activated. (thanks, @johandouma).

## 2.1.4 - 2019-01-08
### Fixed
- Makes URL encoding of file paths for Imgix RFC 3986 compliant (#190) (thanks, @Mosnar).
- Fixes in issue with running Imager transforms from the command line (thanks, @janhenckens).

## 2.1.3 - 2018-12-31
### Fixed
- Default quality is no longer sent to Imgix when auto compression is enabled (thanks, @jorenvanhee).

## 2.1.2 - 2018-11-27
### Added
- Added support for purging images from Imgix (thanks, @mmikkel).

## 2.1.1 - 2018-11-01
### Fixed
- Fixes an issue where the image driver would not be detected when using the static method hasSupportForWebP before the service was constructed. 
- Fixes an issue where if the the remote filename is invalid the filename would be invalid locally and could not be created, this sanitizes the filename that will be created to it is always valid even if the remote filename is invalid (Thanks, @HelgeSverre).
- Fixes an issue with filename collisions when creating temporary filename, microtime() is now used instead of time() (thanks, @MflJoe).
 
## 2.1.0 - 2018-07-28
### Added
- Added a ton of color utility template variables for getting brightness, hue, lightness, percieved brightness, relative luminance, saturation, brightness difference, color difference and (puh!) contrast ratio. 
 
### Changed
- Changed check for when to apply background colors. GIFs and PNGs can haz too.   
- Changed composer dependency for imgix/imgix-php (#181).   

## 2.0.2 - 2018-07-13
### Fixed
- Fixes incorrect slashes in generated transform URLs on windows (#179).   
- Fixes bug where it was not possible to create transparent gif placeholders (#178).
- Docs now mentions how to use Craft's built in asset focal point with position. Plus other minor updates.   

## 2.0.1.2 - 2018-06-12
### Fixed
- Changed composer dependency for tinify/tinify to allow older versions without dependecy for libcurl >=7.20.0.   

## 2.0.1.1 - 2018-05-14
### Fixed
- Also improved check for native transforms using Imager to make sure we're dealing only with images.   

## 2.0.1 - 2018-05-13
### Fixed
- Fixed an issue that could occur if an object was passed as a transform object instead of an array (Thanks, @Rias500!).
- Improved the check for when to create thumbnails to make sure we're dealing only with images (Thanks, @Rias500!).   

## 2.0.0 - 2018-03-30
### Added
- Documentation done, bumbed to 2.0.0. 

## 2.0.0-beta4 - 2018-03-27
### Added
- Added new placeholder template variable that replaces base64Pixel. Placeholders can now be SVG, GIF or SVG silhouettes.
- Added Yii alias support to imagerSystemPath setting, and replaces DOCUMENT_ROOT with Craft @webroot alias (Thanks, @mmikkel!).

## 2.0.0-beta3 - 2018-03-01
### Fixed
- Fixed use of `Asset::getUri()` which was deprecated in RC13.

## 2.0.0-beta2 - 2018-02-26
### Added
- Support for using Imager for native transforms (`useForNativeTransforms` config setting) and control panel thumbs (`useForCpThumbs` config setting). Very beta atm.
- Added support for native focal point in `position`.
- Added support for aliases in `imagerSystemPath` and `imagerUrl`.
- Added config setting `cacheRemoteFiles` to enable and disable caching of remote images (enabled by default).

### Changed
- The `suppressExceptions` config setting now uses `devMode` by default to determine initial value. Still possible to override though.
- Improved error handling. More annotations and code documentation.

### Fixed
- Fixed a bug where `serverSupportsWebp` would throw an error if config was not initialized.

## 2.0.0-beta1 - 2018-02-10
### Added
- Initial Craft 3 beta release
