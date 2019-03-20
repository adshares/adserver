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

declare(strict_types = 1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Common\Application\Service\LicenseDecoder;
use Adshares\Common\Application\Service\LicenseProvider;
use Adshares\Common\Application\Service\LicenseVault;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Illuminate\Console\Command;

class FetchLicenseCommand extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:license:fetch';

    protected $description = 'Fetch operator license from License Server';
    /** @var LicenseProvider */
    private $license;
    /** @var LicenseDecoder */
    private $licenseDecoder;
    /** @var LicenseVault */
    private $licenseVault;

    public function __construct(LicenseProvider $license, LicenseDecoder $licenseDecoder, LicenseVault $licenseVault)
    {
        $this->license = $license;

        parent::__construct();
        $this->licenseDecoder = $licenseDecoder;
        $this->licenseVault = $licenseVault;
    }

    public function handle(): void
    {
        $this->info('Start command '.$this->signature);

        try {
            $encodedLicense = $this->license->fetchLicense();
            $this->licenseDecoder->decode($encodedLicense->toString());
            $this->licenseVault->store($encodedLicense->toString());
        } catch (UnexpectedClientResponseException|RuntimeException $exception) {
            $this->error($exception->getMessage());
            return;
        }

        $this->info('License has been downloaded.');
    }
}
