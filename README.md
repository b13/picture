![CI](https://github.com/b13/picture/actions/workflows/ci.yml/badge.svg)

# b13 Image View Helper

## What it does
The b13 image view helper is a massive extension of the regular Fluid image ViewHelper. Basically it processes images
and renders a single src element or a picture element depending on the specified configuration.

## Installation

Install the extension using composer: `composer req b13/picture`.

Include the TypoScript within your main template: 

```
@import 'EXT:picture/Configuration/TypoScript/setup.typoscript'
```

## Use Fluid Namespace `B13\Picture`

Use a proper configured Fluid template adding the namespace when using this ViewHelper:

```
<html
  xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
  xmlns:i="http://typo3.org/ns/B13/Picture/ViewHelpers"
  data-namespace-typo3-fluid="true"
>
```

## TypoScript setup

See `EXT:picture/Configuration/TypoScript/setup.typoscript` for possible configuration options (key: `plugin.tx_picture`):

| TypoScript Configuration option | Description                                                                                                                                                                                            |
|---------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| addWebp                         | Add webp alternative image files as sources. <br>_default: 0_                                                                                                                                          |
| onlyWebp                        | Enable only images in webp format and for all size variants. <br>_default: 0_                                                                                                                          |
| srcPrefix                       | Enable data-* prefix to all src and srcset. <br>_default: 0_                                                                                                                                           |
| useRetina                       | Add retina (2x) version of all images as sizes variants. <br>_default: 0_                                                                                                                              |
| lossless                        | Enable lossless compression for webp images. <br>_default: 0_                                                                                                                                          |
| retina                          | Use custom or multiple multipliers for calculating retina image variants. <br>_default: <br>retina.2 = 2x<br>Only works in combination with `useRetina = 1`                                            |
| breakpoints                     | Use named breakpoints for easier markup of different image sizes for one picture element.<br>_default: empty_.                                                                                         |
| lazyLoading                     | Use the `loading` attribute with images. See [Browser Native Lazy Loading by Default](https://b13.com/blog/lazy-loading-just-got-lazier-in-typo3-v10)<br>_default: {$types.content.image.lazyLoading}_ |

## Attributes

### All from f:image
Our image ViewHelper mimics the Fluid Image ViewHelper, so it has all the same attributes, including:
* `width` and `height`, including `c` option for crop scaling
* `maxWidth` for proportional scaling, without upscaling
* `fileExtension` to set a file extension (to force webp for example)
* `alt` and `title`
* `cropVariant`
* `loading` to enable [browser native lazy loading by default](https://b13.com/blog/lazy-loading-just-got-lazier-in-typo3-v10).

### useRetina
If useRetina is set and not further specified in TypoScript setup, the corresponding `img` tag's or `source` tag’s
attribute `srcset` is extended by a 2x retina version of the image.

### addWebp
Adds rendering of additional images in webp format. If it is specified without a given sources attribute, it renders a
picture tag instead of a single img tag in order to maintain a browser fallback. If it is specified together with
`sources` it adds an additional `source` tag above any `source` tag rendered by a given `sources` element.
This attribute is ignored if `onlyWebp` option is active.

### onlyWebp
Enable only images in webp format and for all size variants.
Enabling this option disables `addWebp` setting.

### srcPrefix
Enable `data-*` prefix to all `src` and `srcset` allowing to use all king do JS lazy load images libraries. Can be used 
in cases when standard `loading="lazy"` is not enough.

### lossless
Enable lossless compression for webp images. If you find your webp images lacking in quality compared to jpg/png images, enable
this option to overwrite default settings for ImageMagick/GraphicsMagick. 

### variants and sizes
Adds multiple variants of an image with different image sizes, optionally add a sizes-attribute to image tags:

```
variants="400, 600, 800, 1000" sizes="(min-width: 600px) 400px, 100vw"
```
This can also be part of the `sources`-Array (see below).

### sources
Sources must be notated as array. For each element given a source tag is rendered. It accepts the same attributes as
the fluid image view helper. The source tags are rendered in the same ordering as specified in the array. If you do not
specify additional TypoScript settings, any key can be used.
```
sources="{
    0: {
        width: '300c', height: '300c', media: 'min-width: 1000px', cropVariant: 'desktop', variants: '400, 600, 800', sizes: '100vw'
    },
    1: {
        width: '250c', height: '250c', media: 'min-width: 600px', src: alternativefile.uid, treatIdAsReference: 1
    },
    2: {
        width: '200c', height: '200c', media: 'min-width: 300px', cropVariant: 'teaser'
    }
}"
```

### pictureClass
Add a CSS class used for the `picture` element (if rendered using `<picture>`).

## TypoScript Settings

### In general
The following attributes can also be set in TypoScript as defaults for your whole site: `addWebp`, `useRetina`. 
A default setting can be overridden for each usage of the ViewHelper by setting the corresponding attribute.

### retina
The `retina` option enables an extension of the default behaviour of the `useRetina` attribute. If `retina` is set, an
array should be specified with the multiplier for the image size as key, and the multiplier value output in the
corresponding tag.

```
retina {
    2 = 2x
    3 = 3x
}
```

### breakpoints
With the array `breakpoints` you can use those settings by using keys in your Fluid template (instead of adding media
queries for every key in your `sources` array). It simply adds a media query for min-width.

```
breakpoints {
    sm = 640
    md = 1024
    lg = 1280
}
```

## Test rendering for demonstration purposes
You can include a test configuration to see the ViewHelper in your test instance frontend in action:

`@import 'EXT:picture/Configuration/TypoScript/test.typoscript'`

This configuration enables frontend rendering of the test file with lots of different rendering examples using the 
page type `1573387706874`. 

`https://your.local.test.environment/?type=1573387706874`

will render a page with different options to showcase code examples. This is intended for demonstration and testing
purposes, not meant for your production environment.

## Credits

This extension was created by Andreas Hämmerl and David Steeb in 2019 for b13 GmbH, Stuttgart.

[Find more TYPO3 extensions we have developed](https://b13.com/useful-typo3-extensions-from-b13-to-you) that help us
deliver value in client projects. As part of the way we work, we focus on testing and best practices ensuring long-term
performance, reliability, and results in all our code. 
