<?php
/**
 * Created by PhpStorm.
 * User: jacek
 * Date: 01.10.19
 * Time: 15:23
 */

namespace Adshares\Lib;


class DOMDocumentSafe extends \DOMDocument
{
    public function saveHTML()
    {
        $xpath = new \DOMXPath($this);
        $script_texts = [];
        $scripts = $xpath->query("//script");
        foreach ($scripts as $script) {
            $key = bin2hex(random_bytes(16));

            $script_texts[$key] = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $script->textContent);
            $script->textContent = $key;
        }

        $html = parent::saveHTML();

        return $this->fillScripts($html, $script_texts);
    }

    private function fillScripts($html, $script_texts) {
        foreach($script_texts as $hash => $data) {
            $html = str_replace($hash, $data, $html);
        }
        return $html;
    }
}