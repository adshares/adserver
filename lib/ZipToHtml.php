<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Lib;

use Adshares\Common\Exception\RuntimeException;
use DOMXPath;
use finfo;
use ZipArchive;

class ZipToHtml
{
    private const DOMAIN_WHITELIST = [
        'googleapis.com',
        'gstatic.com',
        'code.createjs.com',
        '2mdn.net',
    ];
    private const MAX_ZIPPED_SIZE = 512 * 1024;
    private const MAX_UNZIPPED_SIZE = self::MAX_ZIPPED_SIZE * 5;

    private array $assets = [];
    private ?string $htmlFile;
    private ?string $htmlFileContents;

    public function __construct(private readonly string $filename)
    {
    }

    private function isWhitelisted($href): bool
    {
        $domain = parse_url($href, PHP_URL_HOST);
        foreach (self::DOMAIN_WHITELIST as $allow) {
            if (preg_match('#' . preg_quote($allow, '#') . '$#i', $domain)) {
                return true;
            }
        }

        return false;
    }

    public function getHtml(): string
    {
        $this->assets = [];
        $this->htmlFile = null;
        $this->htmlFileContents = null;
        $this->loadFile();

        return $this->flattenHtml();
    }

    private function loadFile(): void
    {
        $zippedSize = filesize($this->filename);
        if ($zippedSize >= self::MAX_ZIPPED_SIZE) {
            throw new RuntimeException(
                sprintf(
                    "Zip file max size exceeded (%d KB > %d KB)  ",
                    $zippedSize / 1024,
                    self::MAX_ZIPPED_SIZE / 1024
                )
            );
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $unzippedSize = 0;
        $zip = new ZipArchive();

        if (true === $zip->open($this->filename)) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $statistics = $zip->statIndex($i);
                $size = $statistics['size'];
                $unzippedSize += $size;
                if ($unzippedSize >= self::MAX_UNZIPPED_SIZE) {
                    throw new RuntimeException(
                        sprintf(
                            "Zip file max uncompressed size exceeded (%d KB > %d KB)",
                            $unzippedSize / 1024,
                            self::MAX_UNZIPPED_SIZE / 1024
                        )
                    );
                }

                $name = $statistics['name'];
                if (preg_match("#(/|^)(__|\.)#", $name)) {
                    continue;
                }
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if ($size > 0 && false !== ($contents = $zip->getFromIndex($i))) {
                    if ($ext === 'html' || $ext === 'htm') {
                        if ($this->htmlFile) {
                            throw new RuntimeException("Zip file must contain only one html file");
                        }
                        $this->htmlFile = $name;
                        $this->htmlFileContents = $contents;
                    } else {
                        $this->assets[$name] = [
                            'contents' => $contents,
                            'type' => $ext,
                            'mime_type' => $finfo->buffer($contents),
                            'data_uri' => null,
                        ];
                    }
                }
            }

            $zip->close();

            if (!$this->htmlFile) {
                throw new RuntimeException("Zip file must contain a html file");
            }
        }
    }

    private function flattenHtml(): string
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocumentSafe();
        $doc->loadHTML(mb_convert_encoding($this->htmlFileContents, 'HTML-ENTITIES', 'UTF-8'));

        $xpath = new DOMXPath($doc);

        [$body] = $xpath->query('//body');

        if (!$body) {
            throw new RuntimeException('Missing BODY tag');
        }

        if (empty($xpath->query('//head'))) {
            [$html] = $xpath->query('//html');

            if (!$html) {
                throw new RuntimeException('Missing HTML tag');
            }

            $html->insertBefore($doc->createElement('head'), $body);
        }

        $stylesheets = $xpath->query("//link[@rel='stylesheet']");

        foreach ($stylesheets as $sheet) {
            $href = $sheet->getAttribute('href');

            $scheme = parse_url($href, PHP_URL_SCHEME);
            if ($scheme) {
                if ($this->isWhitelisted($href)) {
                    $file = $this->getAssetDataUriExternal($href);
                } else {
                    if ($scheme !== 'data') {
                        throw new RuntimeException(
                            sprintf('Only local assets and data uri allowed (found %s)', $href)
                        );
                    }

                    continue;
                }
            } else {
                $file = $this->normalizePath(dirname($this->htmlFile) . '/' . $href);
            }
            if (isset($this->assets[$file]) && !isset($this->assets[$file]['used'])) {
                $this->assets[$file]['used'] = true;
                $cssText = $this->replaceCssUrls($this->assets[$file]['contents'], dirname($file));
                $newTag = $doc->createElement('style');
                $newTag->textContent = $cssText;
                $newTag->setAttribute('data-inject', "1");
                $newTag->setAttribute('data-href', $file);
                $sheet->parentNode->replaceChild($newTag, $sheet);
            } else {
                $sheet->parentNode->removeChild($sheet);
            }
        }

        $scripts = $xpath->query("//script[@src]");

        foreach ($scripts as $script) {
            $href = $script->getAttribute('src');
            $scheme = parse_url($href, PHP_URL_SCHEME);
            if ($scheme) {
                if ($scheme === 'data') {
                    continue;
                } elseif (!$this->isWhitelisted($href)) {
                    throw new RuntimeException(
                        sprintf("Only local assets and data uri allowed (found %s)", $href)
                    );
                }
                $file = $this->getAssetDataUriExternal($href);
            } else {
                $file = $this->normalizePath(dirname($this->htmlFile) . '/' . $href);
            }

            if (isset($this->assets[$file]) && !isset($this->assets[$file]['used'])) {
                $this->assets[$file]['used'] = true;
                $scriptText = $this->assets[$file]['contents'];
                $newTag = $doc->createElement('script');

                $newTag->textContent = $scriptText;

                $newTag->setAttribute('data-inject', "1");
                $newTag->setAttribute('data-href', $file);
                $script->parentNode->replaceChild($newTag, $script);

                $this->includeCreateJsFix($newTag, $scriptText);
            } else {
                $script->parentNode->removeChild($script);
            }
        }

        $media = $xpath->query("//img[@src]|//input[@src]|//audio[@src]|//video[@src]" .
            "|//source[@src]|//gwd-image[@source]|//amp-img[@src]");
        foreach ($media as $tag) {
            if ($tag->hasAttribute('src')) {
                $attr = 'src';
            } elseif ($tag->hasAttribute('source')) {
                $attr = 'source';
            }
            $href = $tag->getAttribute($attr);
            $scheme = parse_url($href, PHP_URL_SCHEME);
            if ($scheme) {
                if ($scheme === 'data') {
                    continue;
                } elseif (!$this->isWhitelisted($href)) {
                    throw new RuntimeException(
                        sprintf("Only local assets and data uri allowed (found %s)", $href)
                    );
                }
            }

            $file = $this->normalizePath(dirname($this->htmlFile) . '/' . $href);
            if (isset($this->assets[$file])) {
                if (isset($this->assets[$file]['used'])) {
                    $tag->removeAttribute($attr);
                    $tag->setAttribute('data-asset-src', $file);
                } else {
                    $this->assets[$file]['used'] = true;
                    $tag->setAttribute('data-asset-org', $file);
                    $tag->setAttribute($attr, $this->getAssetDataUri($this->assets[$file]));
                }
            } else {
                $tag->removeAttribute('src');
            }
        }

        $media = $xpath->query("//img[@srcset]|//source[@srcset]");
        foreach ($media as $tag) {
            $newsrcset = preg_replace_callback(
                '/([^"\'\s,]+)\s*(\s+\d+[wxh])(,\s*)?+/',
                function ($match) {
                    $href = $match[1];
                    $scheme = parse_url($href, PHP_URL_SCHEME);
                    if ($scheme) {
                        if ($scheme === 'data') {
                            return $href . $match[2] . ($match[3] ?? '');
                        } elseif (!$this->isWhitelisted($href)) {
                            throw new RuntimeException(
                                sprintf("Only local assets and data uri allowed (found %s)", $href)
                            );
                        }
                    }
                    $file = $this->normalizePath(dirname($this->htmlFile) . '/' . $href);
                    if (isset($this->assets[$file])) {
                        if (isset($this->assets[$file]['used'])) {
                            return 'asset-src:' . $file . $match[2] . ($match[3] ?? '');
                        } else {
                            $this->assets[$file]['used'] = true;

                            return $this->getAssetDataUri($this->assets[$file]) . $match[2] . ($match[3] ?? '');
                        }
                    } else {
                        return '';
                    }
                },
                $tag->getAttribute('srcset')
            );

            $tag->setAttribute('srcset', $newsrcset);
        }

        $media = $xpath->query("//video|//audio");
        foreach ($media as $tag) {
            $tag->removeAttribute('autoplay');
        }

        foreach ($this->assets as $file => &$asset) {
            if (!isset($asset['used'])) {
                $asset['used'] = true;
                $tag = $doc->createElement('input');
                $tag->setAttribute('type', 'hidden');
                $tag->setAttribute('style', 'display:none');
                $tag->setAttribute('data-asset-org', $file);
                $tag->setAttribute('data-src', $this->getAssetDataUri($asset));
                $body->appendChild($tag);
            }
        }

        $fixScript = $doc->createElement('script');
        $fixScript->textContent = file_get_contents(resource_path('js/demand/ziptohtml/fixscript.js'));
        $fixScript->setAttribute('data-inject', '1');
        $body->appendChild($fixScript);

        $bannerScript = $doc->createElement('script');
        $bannerScript->textContent = file_get_contents(public_path('-/banner.js'));
        $bannerScript->setAttribute('data-inject', '1');
        $body->insertBefore($bannerScript, $body->firstChild);

        return $doc->saveHTML();
    }


    private function normalizePath($path): string
    {
        $parts = [];// Array to build a new path from the good parts
        $path = str_replace('\\', '/', $path);// Replace backslashes with forwardslashes
        $path = preg_replace('/\/+/', '/', $path);// Combine multiple slashes into a single slash
        $segments = explode('/', $path);// Collect path segments
        $test = '';// Initialize testing variable
        foreach ($segments as $segment) {
            if ($segment != '.') {
                $test = array_pop($parts);
                if (is_null($test)) {
                    $parts[] = $segment;
                } else {
                    if ($segment === '..') {
                        if ($test === '..') {
                            $parts[] = $test;
                        }

                        if ($test === '..' || $test === '') {
                            $parts[] = $segment;
                        }
                    } else {
                        $parts[] = $test;
                        $parts[] = $segment;
                    }
                }
            }
        }

        $url = implode('/', $parts);
        if (($n = strpos($url, '?')) !== false) {
            $url = substr($url, 0, $n);
        }

        return $url;
    }

    private function replaceCssUrls($cssText, $basedir)
    {
        $uriChars = preg_quote('-._~:/?#[]@!$&\'()*+,;=', '#');

        return preg_replace_callback(
            '#url\(\s*[\'"]?([0-9a-z' . $uriChars . ']+?)[\'"]?\s*\)#im',
            function ($match) use ($basedir) {
                $href = $match[1];
                $scheme = parse_url($href, PHP_URL_SCHEME);
                if ($scheme) {
                    if ($this->isWhitelisted($href)) {
                        $file = $this->getAssetDataUriExternal($href);
                    } else {
                        if ($scheme === 'data') {
                            return $match[0];
                        } else {
                            throw new RuntimeException(
                                sprintf("Only local assets and data uri allowed (found %s)", $href)
                            );
                        }
                    }
                } else {
                    $file = $this->normalizePath($basedir . '/' . $href);
                }

                return '/*{asset-src:' . $file . '}*/';
            },
            $cssText
        );
    }

    private function getAssetDataUri(array &$asset)
    {
        if (!$asset['data_uri']) {
            $asset['data_uri'] = "data:{$asset['mime_type']};base64," . base64_encode($asset['contents']);
        }

        return $asset['data_uri'];
    }

    private function getAssetDataUriExternal($url)
    {
        if (!$this->isWhitelisted($url)) {
            throw new RuntimeException("URL is not whitelisted");
        }

        $name = sha1($url);
        if (!isset($this->assets[$name])) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $contents = curl_exec($ch);
            $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            $this->assets[$name] = [
                'contents' => $contents,
                'type' => '',
                'mime_type' => $mime,
                'data_uri' => null,

            ];
        }

        return $name;
    }

    private function includeCreateJsFix($element, $text): void
    {
        if (str_contains($text, 'createjs.com')) {
            $fixScript = $element->ownerDocument->createElement('script');
            $fixScript->textContent = file_get_contents(resource_path('js/demand/ziptohtml/createjs_fix.js'));

            $element->parentNode->insertBefore($fixScript, $element->nextSibling);
        }
    }
}
