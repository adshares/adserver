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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\BidStrategyDetail;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use DateTimeImmutable;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class BidStrategyControllerTest extends TestCase
{
    private const URI = '/api/campaigns/bid-strategy';

    private const URI_UUID_GET = '/api/campaigns/bid-strategy/uuid-default';

    private const URI_UUID_PUT = '/admin/campaigns/bid-strategy/uuid-default';

    private const STRUCTURE_CHECK = [
        [
            'uuid',
            'name',
            'details' => [
                '*' => [
                    'category',
                    'rank',
                ],
            ],
        ],
    ];

    private const DATA = [
        'name' => 'test-name',
        'details' => [
            [
                'category' => 'user:country:us',
                'rank' => 1,
            ],
            [
                'category' => 'user:country:other',
                'rank' => 0.2,
            ],
        ],
    ];

    public function testInitialBidStrategyWithoutDefault(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0);
    }

    public function testInitialBidStrategyWithDefault(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI . '?attach-default=true');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::STRUCTURE_CHECK);
        $response->assertJsonCount(1);
    }

    public function testAddBidStrategy(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $responsePut = $this->putJson(self::URI, self::DATA);
        $responsePut->assertStatus(Response::HTTP_CREATED);

        $responseGet = $this->getJson(self::URI);
        $responseGet->assertStatus(Response::HTTP_OK);
        $responseGet->assertJsonStructure(self::STRUCTURE_CHECK);
        $responseGet->assertJsonCount(1);

        $content = json_decode($responseGet->getContent(), true);
        $entry = $content[0];
        self::assertEquals(self::DATA['name'], $entry['name']);
        self::assertEquals(self::DATA['details'], $entry['details']);
    }

    public function testAddBidStrategyReachLimit(): void
    {
        $limit = 20;
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        factory(BidStrategy::class)->times($limit)->create(['user_id' => $user->id]);

        $responsePut = $this->putJson(self::URI, self::DATA);
        $responsePut->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testEditOwnBidStrategy(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $bidStrategy = BidStrategy::register('test', $user->id);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patchJson(self::URI . '/' . $bidStrategyPublicId, self::DATA);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $bidStrategyEdited = BidStrategy::fetchByPublicId($bidStrategyPublicId)->toArray();
        self::assertEquals(self::DATA['name'], $bidStrategyEdited['name']);
        self::assertEquals(self::DATA['details'], $bidStrategyEdited['details']);
    }

    public function testEditAlienBidStrategy(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $bidStrategy = BidStrategy::register('test', $user->id + 1);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patchJson(self::URI . '/' . $bidStrategyPublicId, self::DATA);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testEditNotExistingBidStrategy(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $bidStrategyPublicId = '0123456789abcdef0123456789abcdef';

        $response = $this->patchJson(self::URI . '/' . $bidStrategyPublicId, self::DATA);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testEditInvalidBidStrategy(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $bidStrategyInvalidPublicId = 1000;

        $response = $this->patchJson(self::URI . '/' . $bidStrategyInvalidPublicId, self::DATA);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDbConnectionErrorWhileAddingBidStrategy(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException());
        DB::shouldReceive('rollback')->andReturnUndefined();

        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->putJson(self::URI, self::DATA);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testDbConnectionErrorWhileEditingBidStrategy(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException());
        DB::shouldReceive('rollback')->andReturnUndefined();

        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $bidStrategy = BidStrategy::register('test', $user->id);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patchJson(self::URI . '/' . $bidStrategyPublicId, self::DATA);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @dataProvider invalidBidStrategyDataProvider
     *
     * @param array $data
     */
    public function testAddingBidStrategyInvalid(array $data): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->putJson(self::URI, $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function invalidBidStrategyDataProvider(): array
    {
        return [
            'empty-name' => [
                [
                    'name' => '',
                    'details' => [
                        [
                            'category' => 'user:country:us',
                            'rank' => 0.1,
                        ],
                    ],
                ],
            ],
            'rank-too-low' => [
                [
                    'name' => 'test-name',
                    'details' => [
                        [
                            'category' => 'user:country:us',
                            'rank' => -0.1,
                        ],
                    ],
                ],
            ],
            'rank-too-high' => [
                [
                    'name' => 'test-name',
                    'details' => [
                        [
                            'category' => 'user:country:us',
                            'rank' => 2,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testGetDefaultUuid(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI_UUID_GET);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['uuid']);
    }

    public function testPutDefaultUuidValid(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create(['is_admin' => 1]);
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', BidStrategy::ADMINISTRATOR_ID);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $responsePut = $this->put(self::URI_UUID_PUT, ['uuid' => $bidStrategyPublicId]);
        $responsePut->assertStatus(Response::HTTP_NO_CONTENT);

        $responseGet = $this->getJson(self::URI_UUID_GET);
        $responseGet->assertStatus(Response::HTTP_OK);
        $responseGet->assertJsonStructure(['uuid']);
        self::assertEquals($bidStrategyPublicId, $responseGet->json('uuid'));
    }

    public function testPutDefaultUuidInvalid(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->put(self::URI_UUID_PUT, ['uuid' => '1234']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPutDefaultUuidNotExisting(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->put(self::URI_UUID_PUT, ['uuid' => '00000000000000000000000000000000']);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testPutDefaultUuidForbidden(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create(['is_admin' => 0]);
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', $user->id);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->put(self::URI_UUID_PUT, ['uuid' => $bidStrategyPublicId]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testPutDefaultUuidOtherUser(): void
    {
        /** @var User $userAdmin */
        $userAdmin = factory(User::class)->create(['is_admin' => 1]);
        $this->actingAs($userAdmin, 'api');
        /** @var User $userOther */
        $userOther = factory(User::class)->create(['is_admin' => 0]);
        $bidStrategy = BidStrategy::register('test', $userOther->id);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->put(self::URI_UUID_PUT, ['uuid' => $bidStrategyPublicId]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testPutDefaultUuidDbConnectionError(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException());
        DB::shouldReceive('rollback')->andReturnUndefined();

        /** @var User $user */
        $user = factory(User::class)->create(['is_admin' => 1]);
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', BidStrategy::ADMINISTRATOR_ID);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->put(self::URI_UUID_PUT, ['uuid' => $bidStrategyPublicId]);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testDownloadSpreadsheet(): void
    {
        $bidStrategyName = 'test';
        $bidStrategyCategory = 'user:country:af';
        $bidStrategyRank = 0.3;

        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register($bidStrategyName, $user->id);
        $bidStrategy->bidStrategyDetails()->save(BidStrategyDetail::create($bidStrategyCategory, $bidStrategyRank));
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->get(self::URI . '/' . $bidStrategyPublicId . '/spreadsheet');
        $response->assertStatus(Response::HTTP_OK);
        $fileName = 'test.xlsx';
        Storage::put($fileName, $response->streamedContent());
        $fileNameWithPath = Storage::path('') . $fileName;
        $reader = IOFactory::createReaderForFile($fileNameWithPath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($fileNameWithPath);

        $sheets = $spreadsheet->getAllSheets();
        $sheetsCount = count($sheets);
        $mainSheet = $sheets[0];
        $name = $mainSheet->getCellByColumnAndRow(2, 1)->getValue();

        self::assertEquals($bidStrategyName, $name);

        $bidStrategyDetails = [];
        for ($i = 1; $i < $sheetsCount; $i++) {
            $sheet = $sheets[$i];
            $row = 2;

            while (null !== ($value = $sheet->getCellByColumnAndRow(3, $row)->getValue())) {
                if (100 !== $value) {
                    $category = $sheet->getCellByColumnAndRow(1, $row)->getValue();
                    $bidStrategyDetails[] = [
                        'category' => $category,
                        'rank' => (float)$value / 100,
                    ];
                }

                ++$row;
            }
        }

        self::assertCount(1, $bidStrategyDetails);
        self::assertEquals($bidStrategyCategory, $bidStrategyDetails[0]['category']);
        self::assertEquals($bidStrategyRank, $bidStrategyDetails[0]['rank']);

        Storage::delete($fileName);
    }

    public function testUploadSpreadsheetMissingFile(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', $user->id);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->post(self::URI . '/' . $bidStrategyPublicId . '/spreadsheet');
        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function testUploadSpreadsheetInvalidMimeType(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', $user->id);
        $bidStrategyPublicId = $bidStrategy->uuid;
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->post(self::URI . '/' . $bidStrategyPublicId . '/spreadsheet', ['file' => $file]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUploadSpreadsheetDbConnectionError(): void
    {
        $bidStrategyName = 'test-bis';
        $bidStrategyCategory = 'user:country:af';
        $bidStrategyRank = 0.3;
        $bidStrategyData = [[$bidStrategyCategory, $bidStrategyRank * 100]];

        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', $user->id);
        $bidStrategyPublicId = $bidStrategy->uuid;
        $fileName = 'text.xlsx';
        $fileNameWithPath = Storage::path('') . $fileName;
        $this->generateXlsx($fileNameWithPath, $bidStrategyName, $bidStrategyData);
        $file = new UploadedFile(
            $fileNameWithPath,
            $fileName,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();

        $response = $this->post(self::URI . '/' . $bidStrategyPublicId . '/spreadsheet', ['file' => $file]);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);

        Storage::delete($fileName);
    }

    public function testUploadSpreadsheetValid(): void
    {
        $bidStrategyName = 'test';
        $bidStrategyCategory = 'user:country:af';
        $bidStrategyRank = 0.3;
        $bidStrategyData = [[$bidStrategyCategory, $bidStrategyRank * 100]];

        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register($bidStrategyName, $user->id);
        $bidStrategyPublicId = $bidStrategy->uuid;
        $fileName = 'text.xlsx';
        $fileNameWithPath = Storage::path('') . $fileName;
        $this->generateXlsx($fileNameWithPath, $bidStrategyName, $bidStrategyData);
        $file = new UploadedFile(
            $fileNameWithPath,
            $fileName,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->post(self::URI . '/' . $bidStrategyPublicId . '/spreadsheet', ['file' => $file]);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $updatedBidStrategy = BidStrategy::fetchByPublicId($bidStrategyPublicId);
        $updatedBidStrategyDetails = $updatedBidStrategy->bidStrategyDetails;
        self::assertEquals($bidStrategyName, $updatedBidStrategy->name);
        self::assertCount(1, $updatedBidStrategyDetails);
        self::assertEquals($bidStrategyCategory, $updatedBidStrategyDetails->first()->category);
        self::assertEquals($bidStrategyRank, $updatedBidStrategyDetails->first()->rank);

        Storage::delete($fileName);
    }

    private function generateXlsx(string $fileName, string $bidStrategyName, array $bidStrategyData): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $data = [
            ['Name', $bidStrategyName],
            ['Created', (new DateTimeImmutable())->format(DateTimeImmutable::ATOM)],
        ];

        $y = 1;
        foreach ($data as $row) {
            $x = 1;
            foreach ($row as $cellValue) {
                $sheet->setCellValueByColumnAndRow($x, $y, $cellValue);
                ++$x;
            }

            ++$y;
        }

        $sheet = $spreadsheet->createSheet();
        $data = [
            ['Id', 'Category', 'Value [%]'],
        ];

        foreach ($bidStrategyData as $entry) {
            $data[] = [$entry[0], '', $entry[1]];
        }

        $y = 1;
        foreach ($data as $row) {
            $x = 1;
            foreach ($row as $cellValue) {
                $sheet->setCellValueByColumnAndRow($x, $y, $cellValue);

                ++$x;
            }
            ++$y;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($fileName);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(ConfigurationRepository::class, new DummyConfigurationRepository());
    }
}
