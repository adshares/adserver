<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Services\Supply;

use Adshares\Adserver\Services\Supply\BannerPlaceholderConverter;
use Adshares\Adserver\Services\Supply\BannerPlaceholderProvider;
use Adshares\Adserver\Services\Supply\DefaultBannerPlaceholderGenerator;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Dto\Media;
use Adshares\Common\Application\Dto\TaxonomyV2;
use Adshares\Common\Application\Dto\TaxonomyV2\Format;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Application\Dto\TaxonomyV2\Targeting;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Domain\Adapter\ArrayableItemCollection;
use Adshares\Common\Exception\Exception;
use Adshares\Common\Exception\RuntimeException;

class DefaultBannerPlaceholderGeneratorTest extends TestCase
{
    public function testGenerateImage(): void
    {
        $converter = $this->createMock(BannerPlaceholderConverter::class);
        $converter->expects(self::once())->method('convertToImages');
        $converter->expects(self::never())->method('convertToHtml');
        $converter->expects(self::never())->method('convertToVideos');
        $provider = $this->createMock(BannerPlaceholderProvider::class);
        $provider->expects(self::never())->method('addBannerPlaceholder');
        $provider->expects(self::once())->method('addDefaultBannerPlaceholder');
        $media = new ArrayableItemCollection();
        $targeting = new Targeting(
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
        );
        $formats = new ArrayableItemCollection();
        $formats->add(new Format('image', ['image/png', 'image/jpeg'], ['1x1' => '1']));
        $media->add(new Medium('test', 'test', null, null, $formats, $targeting));
        $taxonomy = $this->createMock(TaxonomyV2::class);
        $taxonomy->method('getMedia')->willReturn($media);
        $simpleMedia = new Media();
        $simpleMedia->add('test', 'test');
        $repository = $this->createMock(ConfigurationRepository::class);
        $repository->method('fetchTaxonomy')->willReturn($taxonomy);
        $repository->method('fetchMedia')->willReturn($simpleMedia);
        $generator = new DefaultBannerPlaceholderGenerator($converter, $provider, $repository);

        $generator->generate();
    }

    public function testGenerateHtmlAndVideo(): void
    {
        $converter = $this->createMock(BannerPlaceholderConverter::class);
        $converter->expects(self::never())->method('convertToImages');
        $converter->expects(self::once())->method('convertToHtml');
        $converter->expects(self::once())->method('convertToVideos');
        $provider = $this->createMock(BannerPlaceholderProvider::class);
        $provider->expects(self::never())->method('addBannerPlaceholder');
        $provider->expects(self::never())->method('addDefaultBannerPlaceholder');
        $media = new ArrayableItemCollection();
        $targeting = new Targeting(
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
        );
        $formats = new ArrayableItemCollection();
        $formats->add(new Format('html', ['text/html'], ['1x1' => '1']));
        $formats->add(new Format('video', ['video/mp4'], ['1x1' => '1']));
        $media->add(new Medium('test', 'test', null, null, $formats, $targeting));
        $taxonomy = $this->createMock(TaxonomyV2::class);
        $taxonomy->method('getMedia')->willReturn($media);
        $simpleMedia = new Media();
        $simpleMedia->add('test', 'test');
        $repository = $this->createMock(ConfigurationRepository::class);
        $repository->method('fetchTaxonomy')->willReturn($taxonomy);
        $repository->method('fetchMedia')->willReturn($simpleMedia);
        $generator = new DefaultBannerPlaceholderGenerator($converter, $provider, $repository);

        $generator->generate();
    }

    public function testGenerateDirect(): void
    {
        $converter = $this->createMock(BannerPlaceholderConverter::class);
        $converter->expects(self::never())->method('convertToImages');
        $converter->expects(self::never())->method('convertToHtml');
        $converter->expects(self::never())->method('convertToVideos');
        $provider = $this->createMock(BannerPlaceholderProvider::class);
        $provider->expects(self::never())->method('addBannerPlaceholder');
        $provider->expects(self::never())->method('addDefaultBannerPlaceholder');
        $media = new ArrayableItemCollection();
        $targeting = new Targeting(
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
        );
        $formats = new ArrayableItemCollection();
        $formats->add(new Format('direct', ['text/plain'], ['pop-up' => 'Pop Up']));
        $media->add(new Medium('test', 'test', null, null, $formats, $targeting));
        $taxonomy = $this->createMock(TaxonomyV2::class);
        $taxonomy->method('getMedia')->willReturn($media);
        $simpleMedia = new Media();
        $simpleMedia->add('test', 'test');
        $repository = $this->createMock(ConfigurationRepository::class);
        $repository->method('fetchTaxonomy')->willReturn($taxonomy);
        $repository->method('fetchMedia')->willReturn($simpleMedia);
        $generator = new DefaultBannerPlaceholderGenerator($converter, $provider, $repository);

        $generator->generate();
    }

    public function testGenerateFail(): void
    {
        $converter = $this->createMock(BannerPlaceholderConverter::class);
        $converter->expects(self::never())->method('convertToImages');
        $converter->expects(self::never())->method('convertToHtml');
        $converter->expects(self::never())->method('convertToVideos');
        $provider = $this->createMock(BannerPlaceholderProvider::class);
        $provider->expects(self::never())->method('addBannerPlaceholder');
        $provider->expects(self::once())
            ->method('addDefaultBannerPlaceholder')
            ->willThrowException(new Exception('test-exception'));
        $media = new ArrayableItemCollection();
        $targeting = new Targeting(
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
        );
        $formats = new ArrayableItemCollection();
        $formats->add(new Format('image', ['image/png'], ['1x1' => '1']));
        $media->add(new Medium('test', 'test', null, null, $formats, $targeting));
        $taxonomy = $this->createMock(TaxonomyV2::class);
        $taxonomy->method('getMedia')->willReturn($media);
        $simpleMedia = new Media();
        $simpleMedia->add('test', 'test');
        $repository = $this->createMock(ConfigurationRepository::class);
        $repository->method('fetchTaxonomy')->willReturn($taxonomy);
        $repository->method('fetchMedia')->willReturn($simpleMedia);
        $generator = new DefaultBannerPlaceholderGenerator($converter, $provider, $repository);

        $this->expectException(RuntimeException::class);

        $generator->generate();
    }

    public function testMergeMediaByName(): void
    {
        $media = new ArrayableItemCollection();
        $targeting = new Targeting(
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
        );
        $formats = new ArrayableItemCollection();
        $formats->add(new Format('image', ['image/png'], ['1x1' => '1', '2x2' => '2']));
        $media->add(new Medium('test', 'test', 'vendor', 'vendor', $formats, $targeting));
        $formats = new ArrayableItemCollection();
        $formats->add(new Format('image', ['image/jpeg', 'image/gif'], ['2x2' => '2']));
        $media->add(new Medium('test', 'test', null, null, $formats, $targeting));

        $merged = DefaultBannerPlaceholderGenerator::mergeMediaByName($media, 'test');

        $format = $merged->getFormats()[0];
        self::assertEquals('image', $format->getType());
        $mimes = $format->getMimes();
        self::assertCount(3, $mimes);
        foreach (['image/png', 'image/jpeg', 'image/gif'] as $mime) {
            self::assertContainsEquals($mime, $mimes);
        }
        $scopes = array_keys($format->getScopes());
        self::assertCount(2, $scopes);
        foreach (['1x1', '2x2'] as $scope) {
            self::assertContainsEquals($scope, $scopes);
        }
    }
}
