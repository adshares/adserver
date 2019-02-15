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
declare(strict_types = 1);

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\ViewModel\OptionsSelector;
use Adshares\Common\Application\Model\Selector;
use Adshares\Common\Application\Model\Selector\Option;
use Adshares\Common\Application\Model\Selector\OptionValue;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class OptionsController extends Controller
{
    /** @var ConfigurationRepository */
    private $optionsRepository;

    public function __construct(ConfigurationRepository $optionsRepository)
    {
        $this->optionsRepository = $optionsRepository;
    }

    public function targeting(): JsonResponse
    {
        $options = $this->optionsRepository->fetchTargetingOptions();

        return self::json(new OptionsSelector($options));
    }

    public function filtering(): JsonResponse
    {
        $options = $this->optionsRepository->fetchFilteringOptions();

        $options->addOption((new Option('string', 'classification', 'Classification', false))
            ->withValues(...[
                new OptionValue('Positive for all my Sites', 'PUBLISHERxYES'),
                new OptionValue('Negative for all my Sites', 'PUBLISHERxNO'),
                new OptionValue('Positive for just this Sites', 'SITExYES'),
                new OptionValue('Negative for just this Sites', 'SITExNO'),
            ]));

        $options->addOption((new Option(null, 'classification2', 'Sublevel Classification', false))
            ->withChildren(new Selector(...[
                (new Option('string', 'classificationXpublisher', 'All my Sites', false))
                    ->withValues(...[
                        new OptionValue('Positive', 'YES'),
                        new OptionValue('Negative', 'NO'),
                    ]),
                (new Option('string', 'classificationXsite', 'Just this Site', false))
                    ->withValues(...[
                        new OptionValue('Positive', 'YES'),
                        new OptionValue('Negative', 'NO'),
                    ]),
            ])));

        return self::json(new OptionsSelector($options));
    }

    public function languages(): JsonResponse
    {
        return self::json(Simulator::getAvailableLanguages());
    }

    public function zones(): JsonResponse
    {
        return self::json(Simulator::getZoneTypes());
    }
}
