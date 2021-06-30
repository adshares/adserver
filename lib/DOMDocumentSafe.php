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

class DOMDocumentSafe extends \DOMDocument
{
    public function saveHTML()
    {
        $xpath = new \DOMXPath($this);
        $script_texts = [];
        $scripts = $xpath->query("//script");
        foreach ($scripts as $script) {
            $key = bin2hex(random_bytes(16));

            $script_texts[$key] = preg_replace_callback("/(&#[0-9]+;)/", function ($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            }, $script->textContent);
            $script->textContent = $key;
        }

        $html = parent::saveHTML();

        return $this->fillScripts($html, $script_texts);
    }

    private function fillScripts($html, $script_texts)
    {
        foreach ($script_texts as $hash => $data) {
            $html = str_replace($hash, $data, $html);
        }
        return $html;
    }
}
