<?php

declare(strict_types=1);

namespace B13\Picture\Tests\Functional\Frontend;

/*
 * This file is part of TYPO3 CMS-based extension "picture" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class TagRenderingTest extends FunctionalTestCase
{
    protected array $pathsToLinkInTestInstance = ['typo3conf/ext/picture/Build/sites' => 'typo3conf/sites'];
    protected array $testExtensionsToLoad = ['typo3conf/ext/picture'];

    /**
     * @test
     */
    public function simpleImage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/simple_image.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = '<img alt="Testimage 400px width" src="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png" width="400" height="200" loading="lazy" />';
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    /**
     * @test
     */
    public function simpleImageAsWebp(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/simple_image_as_webp.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = '<img alt="Testimage 400px width" src="/typo3temp/assets/_processed_/a/2/csm_Picture_cfb567934c.webp" width="400" height="200" loading="lazy" />';
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    /**
     * @test
     */
    public function simpleImageWithOnlyWebp(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/simple_image_with_only_webp_option.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = ' <picture>
<source srcset="typo3temp/assets/_processed_/a/2/csm_Picture_xxx.webp" media="(min-width: 1024px)" />
<img alt="Testimage 400px width" src="typo3temp/assets/_processed_/a/2/csm_Picture_xxx.webp" width="400" height="200" loading="lazy" />
</picture>';
        $expected = implode(' ', GeneralUtility::trimExplode("\n", $expected));
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    /**
     * @test
     */
    public function simpleImageWithRetinaOption(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/simple_image_with_retina_option.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = '<img 
alt="Testimage 400px width with retina option" 
src="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png" 
width="400"
height="200"
loading="lazy" 
srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png, /typo3temp/assets/_processed_/a/2/csm_Picture_13dd378eeb.png 2x, /typo3temp/assets/_processed_/a/2/csm_Picture_3c8b5cfedf.png 3x" />';
        $expected = implode(' ', GeneralUtility::trimExplode("\n", $expected));
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    /**
     * @test
     */
    public function simpleImageWithWebpOption(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/simple_image_with_webp_option.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = '<picture>
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_cfb567934c.webp" type="image/webp" />
<img alt="Testimage 400px width with addWebp option" src="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png" width="400" height="200" loading="lazy" />
</picture>';
        $expected = implode('', GeneralUtility::trimExplode("\n", $expected));
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    /**
     * @test
     */
    public function simpleImageWithRetinaAndWebpOption(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/simple_image_with_retina_and_webp_option.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = '<picture>
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_cfb567934c.webp, /typo3temp/assets/_processed_/a/2/csm_Picture_089357224d.webp 2x, /typo3temp/assets/_processed_/a/2/csm_Picture_d356d2dde1.webp 3x" type="image/webp" />
<img alt="Testimage 400px width with retina and addWebp option " src="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png" width="400" height="200" loading="lazy" srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png, /typo3temp/assets/_processed_/a/2/csm_Picture_13dd378eeb.png 2x, /typo3temp/assets/_processed_/a/2/csm_Picture_3c8b5cfedf.png 3x" />
</picture>';
        $expected = implode('', GeneralUtility::trimExplode("\n", $expected));
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    /**
     * @test
     */
    public function imageWithMultipleSizes(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/image_with_multiple_sizes.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = ' <picture>
 <source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_30b88604ff.png" media="(min-width: 1024px)" />
 <source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png" />
 <img alt="Testimage with 400px image size, 800px image size for screens &gt; 1024px" src="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png" width="400" height="200" loading="lazy" />
 </picture> ';
        $expected = implode('', GeneralUtility::trimExplode("\n", $expected));
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    /**
     * @test
     */
    public function imageWithTwoSizesAndRetinaOption(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/image_with_two_sizes_and_retina_option.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = '<picture>
<source media="(min-width: 1024px)" srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_30b88604ff.png, /typo3temp/assets/_processed_/a/2/csm_Picture_9939a0a20d.png 2x, /typo3temp/assets/_processed_/a/2/csm_Picture_367bb79630.png 3x" />
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png, /typo3temp/assets/_processed_/a/2/csm_Picture_13dd378eeb.png 2x, /typo3temp/assets/_processed_/a/2/csm_Picture_3c8b5cfedf.png 3x" />
<img alt="Testimage with 400px image size, 800px image size for screens &gt; 1024px, with retina" src="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png" width="400" height="200" loading="lazy" srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png, /typo3temp/assets/_processed_/a/2/csm_Picture_13dd378eeb.png 2x, /typo3temp/assets/_processed_/a/2/csm_Picture_3c8b5cfedf.png 3x" />
</picture>';
        $expected = implode('', GeneralUtility::trimExplode("\n", $expected));
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    /**
     * @test
     */
    public function singleImageWithMultipleImageSizesAndTwoBreakpoints(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/single_image_with_multiple_image_sizes_and_two_breakpoints.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = '<picture>
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_724dd3b269.png 310w, /typo3temp/assets/_processed_/a/2/csm_Picture_6cab563075.png 345w, /typo3temp/assets/_processed_/a/2/csm_Picture_703de20dda.png 400w" sizes="100vh" />
<img alt="Testimage with 400px image size, with multiple images as a srcset, including webp image format with fallback" src="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png" width="400" height="200" loading="lazy" />
</picture>';
        $expected = implode('', GeneralUtility::trimExplode("\n", $expected));
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    /**
     * @test
     */
    public function singleImageWithMultipleImageSizesAsSrcset(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/single_image_with_multiple_image_sizes_as_srcset.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = '<img
alt="Testimage with 400px image size, with multiple images as a srcset, including webp image format with fallback" 
srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_37ef2fbec7.png 310w, /typo3temp/assets/_processed_/a/2/csm_Picture_ffcad8bfb4.png 345w, /typo3temp/assets/_processed_/a/2/csm_Picture_cd33d19e9a.png 400w" 
src="/typo3temp/assets/_processed_/a/2/csm_Picture_0d0101f0a6.png" 
sizes="(min-width: 400px) 400px, 100vh" 
width="400" height="200" loading="lazy" />';
        $expected = implode(' ', GeneralUtility::trimExplode("\n", $expected));
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    /**
     * @test
     */
    public function imageWithSrcsetAndASizesValueWithWebpOption(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/image_with_srcset_and_a_sizes_value_with_webp_option.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = '<picture class="myPictureClass">
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_80498eb4cf.webp 800w, /typo3temp/assets/_processed_/a/2/csm_Picture_6546fe1853.webp 1200w, /typo3temp/assets/_processed_/a/2/csm_Picture_fffe3df9da.webp 1600w, /typo3temp/assets/_processed_/a/2/csm_Picture_0a9265a906.webp 2000w" media="(min-width: 1024px)" sizes="100vh" type="image/webp" />
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_39487edcc6.png 800w, /typo3temp/assets/_processed_/a/2/csm_Picture_86fac4fe8a.png 1200w, /typo3temp/assets/_processed_/a/2/csm_Picture_a1c0b8cf78.png 1600w, /typo3temp/assets/_processed_/a/2/csm_Picture_fc98b213ea.png 2000w" media="(min-width: 1024px)" sizes="100vh" />
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_79ce5f6e5c.webp 310w, /typo3temp/assets/_processed_/a/2/csm_Picture_955087c064.webp 345w, /typo3temp/assets/_processed_/a/2/csm_Picture_881ba9c90f.webp 400w" sizes="100vh" type="image/webp" />
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_724dd3b269.png 310w, /typo3temp/assets/_processed_/a/2/csm_Picture_6cab563075.png 345w, /typo3temp/assets/_processed_/a/2/csm_Picture_703de20dda.png 400w" sizes="100vh" />
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_cfb567934c.webp" type="image/webp" />
<img alt="Testimage with 400px image size, 800px image size for screens &gt; 1024px, with multiple images as a srcset, including webp image format with fallback" src="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png" width="400" height="200" loading="lazy" />
</picture>';
        $expected = implode('', GeneralUtility::trimExplode("\n", $expected));
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    /**
     * @test
     */
    public function imageWithThreeSizesForThreeGivenBreakpoints(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/image_with_three_sizes_for_three_given_breakpoints.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = '<picture class="myPictureClass">
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_30b88604ff.png" media="(min-width: 1280px)" />
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_417d139688.png" media="(min-width: 1024px)" />
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png" media="(min-width: 640px)" />
<img alt="Testimage with 3 breakpoints referenced by name" src="/typo3temp/assets/_processed_/a/2/csm_Picture_23f7889ff5.png" width="400" height="200" loading="lazy" />
</picture>';
        $expected = implode('', GeneralUtility::trimExplode("\n", $expected));
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    /**
     * @test
     */
    public function imageWithPictureClassRenderPictureTag(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/image_with_picture_class.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = '<picture class="my-class"><img src="/typo3conf/ext/picture/Resources/Public/Icons/Extension.svg" width="256" height="256" loading="lazy" alt="" /></picture>';
        self::assertStringContainsString($expected, $body);
    }

    public function imageWithMultipleSources(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/image_with_multiple_sources.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = '<picture class="myPictureClass">
<source srcset="/typo3temp/assets/_processed_/a/1/csm_Picture_alt_b2b773d76f.png" media="(min-width: 1024px)" />
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_0d0101f0a6.png" />
<img alt="Testimage with 400px image size, 800px image size with rotated image for screens &gt; 1024px" src="/typo3temp/assets/_processed_/a/2/csm_Picture_0d0101f0a6.png" width="400" height="200" loading="lazy" />
</picture>';
        $expected = implode('', GeneralUtility::trimExplode("\n", $expected));
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    /**
     * @test
     */
    public function imageWithMultipleSourcesWithWebPOption(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/image_with_multiple_sources_with_webp_option.csv');
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $body = (string)$response->getBody();
        $expected = '<picture class="myPictureClass">
<source srcset="/typo3temp/assets/_processed_/a/1/csm_Picture_alt_4b5b9dd7fe.webp" media="(min-width: 1024px)" type="image/webp" />
<source srcset="/typo3temp/assets/_processed_/a/1/csm_Picture_alt_b2b773d76f.png" media="(min-width: 1024px)" />
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_21770d9a15.webp" type="image/webp" />
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_0d0101f0a6.png" />
<source srcset="/typo3temp/assets/_processed_/a/2/csm_Picture_21770d9a15.webp" type="image/webp" />
<img alt="Testimage with 400px image size, 800px image size with rotated image for screens &gt; 1024px, with webP option" src="/typo3temp/assets/_processed_/a/2/csm_Picture_0d0101f0a6.png" width="400" height="200" loading="lazy" />
</picture>';
        $expected = implode('', GeneralUtility::trimExplode("\n", $expected));
        self::assertStringContainsString($this->anonymouseProcessdImage($expected), $this->anonymouseProcessdImage($body));
    }

    protected function anonymouseProcessdImage(string $content): string
    {
        $content = preg_replace('/Picture_[0-9a-z]+\./', 'Picture_xxx.', $content);
        $content = preg_replace('/Picture_alt_[0-9a-z]+\./', 'Picture_alt_xxx.', $content);
        return $content;
    }
}
