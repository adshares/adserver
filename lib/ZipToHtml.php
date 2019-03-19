<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
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

class ZipToHtml
{
    const MAX_ZIPPED_SIZE = 512 * 1024;
    const MAX_UNZIPPED_SIZE = self::MAX_ZIPPED_SIZE * 5;

    const FIX_SCRIPT = <<<MYSCRIPT
(function(){
    var styles = document.getElementsByTagName('style');
    for(var i=0;i<styles.length;i++) {
        var code = styles[i].innerHTML;
        
        code = code.replace(/\/\*\{asset-src:(.*?)\}\*\//, function(match, src) {
            var uri = ''; var org = document.querySelector('[data-asset-org="' + src + '"]');
            if(org) {
                uri = org.getAttribute('src') || org.getAttribute('data-src');
            } 
            return 'url(' + uri + ')';
        }); 
    
        var s = document.createElement('style');
        s.type = 'text/css';
        try {
          s.appendChild(document.createTextNode(code));
        } catch (e) {
          s.text = code;
        }
        styles[i].parentElement.replaceChild(s, styles[i]);
    
    }
    

    var refs = document.querySelectorAll('[data-asset-src]');
    for(var i=0;i<refs.length;i++) {
        var org = document.querySelector('[data-asset-org="' + refs[i].getAttribute('data-asset-src') + '"]');
        if(org) {
            refs[i].setAttribute('src', org.getAttribute('src'));
        }
    }
})();
MYSCRIPT;

    private $filename;
    private $assets = [];
    private $html_file;
    private $html_file_contents;

    public function __construct($filename)
    {
        $this->filename = $filename;
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
            throw new \RuntimeException(
                sprintf(
                    "Zip file max size exceeded (%d KB > %d KB)  ", $zipped_size / 1024, self::MAX_ZIPPED_SIZE / 1024
                )
            );
        }

        $mimes = new \Mimey\MimeTypes();

        $unzipped_size = 0;
        $zip = \zip_open($this->filename);

        if ($zip) {
            while ($zip_entry = \zip_read($zip)) {
                $size = \zip_entry_filesize($zip_entry);
                $unzipped_size += $size;
                if ($unzipped_size >= self::MAX_UNZIPPED_SIZE) {
                    throw new \RuntimeException(
                        sprintf(
                            "Zip file max uncompressed size exceeded (%d KB > %d KB)", $unzipped_size / 1024,
                            self::MAX_UNZIPPED_SIZE / 1024
                        )
                    );
                }

                $name = \zip_entry_name($zip_entry);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if ($size > 0 && \zip_entry_open($zip, $zip_entry, "r")) {

                    if ($ext == 'html' || $ext == 'htm') {
                        if ($this->html_file) {
                            throw new \RuntimeException("Zip file must contain only one html file");
                        }
                        $this->html_file = $name;
                        $this->html_file_contents = \zip_entry_read($zip_entry, $size);
                    } else {
                        $this->assets[$name] = [
                            'contents' => \zip_entry_read($zip_entry, $size),
                            'type' => $ext,
                            'mime_type' => $mimes->getMimeType($ext),
                            'data_uri' => null,

                        ];
                    }

                    \zip_entry_close($zip_entry);
                }

            }

            \zip_close($zip);

            if (!$this->html_file) {
                throw new \RuntimeException("Zip file must contain a html file");
            }
        }
    }

    private function flattenHtml()
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML(mb_convert_encoding($this->html_file_contents, 'HTML-ENTITIES', 'UTF-8'));

        $xpath = new \DOMXPath($doc);
        [$html] = $xpath->query('//html');
        [$body] = $xpath->query('//body');
        [$head] = $xpath->query('//head');

        if (!$head) {
            $head = $doc->createElement('head');
            $html->insertBefore($head, $body);
        }

        $stylesheets = $xpath->query("//link[@rel='stylesheet']");

        foreach ($stylesheets as $sheet) {
            $href = $sheet->getAttribute('href');
            $scheme = parse_url($href, PHP_URL_SCHEME);
            if ($scheme) {
                if ($scheme == 'data') {
                    continue;
                } else {
                    throw new \RuntimeException(sprintf("Only local assets and data uri allowed (found %s)", $href));
                }
            }
            $file = $this->normalizePath(dirname($this->html_file).'/'.$href);
            if (isset($this->assets[$file]) && !isset($this->assets[$file]['used'])) {
                $this->assets[$file]['used'] = true;
                $css_text = $this->replaceCssUrls($this->assets[$file]['contents'], dirname($file));
                $new_tag = $doc->createElement('style');
                $new_tag->nodeValue = $css_text;
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
                if ($scheme == 'data') {
                    continue;
                } else {
                    throw new \RuntimeException(sprintf("Only local assets and data uri allowed (found %s)", $href));
                }
            }
            $file = $this->normalizePath(dirname($this->html_file).'/'.$href);
            if (isset($this->assets[$file]) && !isset($this->assets[$file]['used'])) {
                $this->assets[$file]['used'] = true;
                $script_text = $this->assets[$file]['contents'];
                $new_tag = $doc->createElement('script');
                $new_tag->nodeValue = $script_text;
                $new_tag->setAttribute('data-inject', "1");
                $new_tag->setAttribute('data-href', $file);
                $script->parentNode->replaceChild($new_tag, $script);
            } else {
                $script->parentNode->removeChild($script);
            }
        }

        $media = $xpath->query("//img[@src]|//input[@src]|//audio[@src]|//video[@src]|//source[@src]");
        foreach ($media as $tag) {
            $href = $tag->getAttribute('src');
            $scheme = parse_url($href, PHP_URL_SCHEME);
            if ($scheme) {
                if ($scheme == 'data') {
                    continue;
                } else {
                    throw new \RuntimeException(sprintf("Only local assets and data uri allowed (found %s)", $href));
                }
            }

            $file = $this->normalizePath(dirname($this->html_file).'/'.$href);
            if (isset($this->assets[$file])) {
                if (isset($this->assets[$file]['used'])) {
                    $tag->removeAttribute('src');
                    $tag->setAttribute('data-asset-src', $file);
                } else {
                    $this->assets[$file]['used'] = true;
                    $tag->setAttribute('data-asset-org', $file);
                    $tag->setAttribute('src', $this->getAssetDataUri($this->assets[$file]));

                }
            } else {
                $tag->removeAttribute('src');
            }
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
        $fix_script->nodeValue = self::FIX_SCRIPT;

        $body->appendChild($fix_script);

        return $doc->saveHTML();
    }

    private function normalizePath($path)
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
                    if ($segment == '..') {
                        if ($test == '..') {
                            $parts[] = $test;
                        }

                        if ($test == '..' || $test == '') {
                            $parts[] = $segment;
                        }
                    } else {
                        $parts[] = $test;
                        $parts[] = $segment;
                    }
                }
            }
        }

        return implode('/', $parts);
    }

    private function replaceCssUrls($css_text, $basedir)
    {
        $uri_chars = preg_quote('-._~:/?#[]@!$&\'()*+,;=', '#');

        return preg_replace_callback(
            '#url\(\s*[\'"]?([0-9a-z'.$uri_chars.']+?)[\'"]?\s*\)#im', function ($match) use ($basedir) {
            $scheme = parse_url($match[1], PHP_URL_SCHEME);
            if ($scheme) {
                if ($scheme == 'data') {
                    return $match[0];
                } else {
                    throw new \RuntimeException(
                        sprintf("Only local assets and data uri allowed (found %s)", $match[1])
                    );
                }
            }

            return '/*{asset-src:'.$this->normalizePath($basedir.'/'.$match[1]).'}*/';
        }, $css_text
        );
    }

    private function getAssetDataUri(array &$asset)
    {
        if (!$asset['data_uri']) {
            $asset['data_uri'] = "data:{$asset['mime_type']};base64,".base64_encode($asset['contents']);
        }

        return $asset['data_uri'];
    }
}
