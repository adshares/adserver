<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

use DOMXPath;
use Mimey\MimeTypes;
use RuntimeException;

use function sprintf;
use function zip_close;
use function zip_entry_close;
use function zip_entry_filesize;
use function zip_entry_name;
use function zip_entry_open;
use function zip_entry_read;
use function zip_open;
use function zip_read;

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

    private $filename;

    private $assets = [];

    private $html_file;

    private $html_file_contents;

    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    private function isWhitelisted($href)
    {
        $domain = parse_url($href, PHP_URL_HOST);
        foreach (self::DOMAIN_WHITELIST as $allow) {
            if (preg_match('#' . preg_quote($allow, '#') . '$#i', $domain)) {
                return true;
            }
        }

        return false;
    }

    public function getHtml()
    {
        $this->assets = [];
        $this->html_file = null;
        $this->html_file_contents = null;
        $this->loadFile();

        return $this->flattenHtml();
    }

    private function loadFile()
    {
        $zipped_size = filesize($this->filename);
        if ($zipped_size >= self::MAX_ZIPPED_SIZE) {
            throw new RuntimeException(
                sprintf(
                    "Zip file max size exceeded (%d KB > %d KB)  ",
                    $zipped_size / 1024,
                    self::MAX_ZIPPED_SIZE / 1024
                )
            );
        }

        $mimes = new MimeTypes();

        $unzipped_size = 0;
        $zip = zip_open($this->filename);

        if ($zip) {
            while ($zip_entry = zip_read($zip)) {
                $size = zip_entry_filesize($zip_entry);
                $unzipped_size += $size;
                if ($unzipped_size >= self::MAX_UNZIPPED_SIZE) {
                    throw new RuntimeException(
                        sprintf(
                            "Zip file max uncompressed size exceeded (%d KB > %d KB)",
                            $unzipped_size / 1024,
                            self::MAX_UNZIPPED_SIZE / 1024
                        )
                    );
                }

                $name = zip_entry_name($zip_entry);
                if (preg_match("#(/|^)(__|\.)#", $name)) {
                    continue;
                }
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if ($size > 0 && zip_entry_open($zip, $zip_entry, "r")) {
                    if ($ext === 'html' || $ext === 'htm') {
                        if ($this->html_file) {
                            throw new RuntimeException("Zip file must contain only one html file");
                        }
                        $this->html_file = $name;
                        $this->html_file_contents = zip_entry_read($zip_entry, $size);
                    } else {
                        $this->assets[$name] = [
                            'contents' => zip_entry_read($zip_entry, $size),
                            'type' => $ext,
                            'mime_type' => $mimes->getMimeType($ext),
                            'data_uri' => null,

                        ];
                    }

                    zip_entry_close($zip_entry);
                }
            }

            zip_close($zip);

            if (!$this->html_file) {
                throw new RuntimeException("Zip file must contain a html file");
            }
        }
    }

    private function flattenHtml(): string
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocumentSafe();
        $doc->loadHTML(mb_convert_encoding($this->html_file_contents, 'HTML-ENTITIES', 'UTF-8'));

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
                $file = $this->normalizePath(dirname($this->html_file) . '/' . $href);
            }
            if (isset($this->assets[$file]) && !isset($this->assets[$file]['used'])) {
                $this->assets[$file]['used'] = true;
                $css_text = $this->replaceCssUrls($this->assets[$file]['contents'], dirname($file));
                $new_tag = $doc->createElement('style');
                $new_tag->textContent = $css_text;
                $new_tag->setAttribute('data-inject', "1");
                $new_tag->setAttribute('data-href', $file);
                $sheet->parentNode->replaceChild($new_tag, $sheet);
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
                $file = $this->normalizePath(dirname($this->html_file) . '/' . $href);
            }

            if (isset($this->assets[$file]) && !isset($this->assets[$file]['used'])) {
                $this->assets[$file]['used'] = true;
                $script_text = $this->assets[$file]['contents'];
                $new_tag = $doc->createElement('script');

                $new_tag->textContent = $script_text;

                $new_tag->setAttribute('data-inject', "1");
                $new_tag->setAttribute('data-href', $file);
                $script->parentNode->replaceChild($new_tag, $script);

                $this->includeCreateJsFix($new_tag, $script_text);
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

            $file = $this->normalizePath(dirname($this->html_file) . '/' . $href);
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
                    $file = $this->normalizePath(dirname($this->html_file) . '/' . $href);
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

        $fix_script = $doc->createElement('script');
        $fix_script->textContent = file_get_contents(resource_path('js/demand/ziptohtml/fixscript.js'));
        $fix_script->setAttribute('data-inject', '1');
        $body->appendChild($fix_script);

        $banner_script = $doc->createElement('script');
        $banner_script->textContent = file_get_contents(public_path('-/banner.js'));
        $banner_script->setAttribute('data-inject', '1');
        $body->insertBefore($banner_script, $body->firstChild);

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

    private function replaceCssUrls($css_text, $basedir)
    {
        $uri_chars = preg_quote('-._~:/?#[]@!$&\'()*+,;=', '#');

        return preg_replace_callback(
            '#url\(\s*[\'"]?([0-9a-z' . $uri_chars . ']+?)[\'"]?\s*\)#im',
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
            $css_text
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

    private function includeCreateJsFix($element, $text)
    {
        if (strstr($text, 'createjs.com')) {
            $fix_script = $element->ownerDocument->createElement('script');
            $fix_script->textContent = file_get_contents(resource_path('js/demand/ziptohtml/createjs_fix.js'));

            $element->parentNode->insertBefore($fix_script, $element->nextSibling);
        }
    }
}
