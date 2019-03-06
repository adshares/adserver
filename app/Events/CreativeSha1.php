<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Events;

use Adshares\Adserver\Models\Banner;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreativeSha1
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(\Adshares\Adserver\Models\Banner $model)
    {
        if ($model->creative_type == Banner::type(Banner::HTML_TYPE)) {
            $model->creative_contents = $this->injectScriptAndCSP($model->creative_contents);
        }

        $model->creative_sha1 = sha1($model->creative_contents);
    }

    private function injectScriptAndCSP($html)
    {
        $jsPath = public_path('-/banner.js');
        $jsCode = file_get_contents($jsPath);

        $doc = new \DOMDocument();
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        $xpath = new \DOMXPath($doc);
        [$html] = $xpath->query('//html');
        [$body] = $xpath->query('//body');
        [$head] = $xpath->query('//head');

        if (!$head) {
            $head = $doc->createElement('head');
            $html->insertBefore($head, $body);
        }

        $metas = $xpath->query('//head/meta');

        $csp_tag = $doc->createElement('meta');
        $csp_tag->setAttribute('http-equiv', "Content-Security-Policy");
        $csp_tag->setAttribute('content', "default-src 'unsafe-inline' data: blob:");

        if (!count($metas) || trim($doc->saveHTML($csp_tag)) != trim($doc->saveHTML($metas[0]))) {
            $head->insertBefore($csp_tag, $head->firstChild);
        }

        $scripts = $xpath->query('//body/script');

        $banner_script = $doc->createElement('script');
        $banner_script->nodeValue = $jsCode;
        if (!count($scripts) || $scripts[0]->nodeValue != $banner_script->nodeValue) {

            $body->insertBefore($banner_script, $body->firstChild);
        }

        return $doc->saveHTML();
    }
}
