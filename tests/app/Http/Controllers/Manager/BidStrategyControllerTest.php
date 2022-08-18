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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\BidStrategyDetail;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class BidStrategyControllerTest extends TestCase
{
    private const URI = '/api/campaigns/bid-strategy';

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
        $this->setupApiRegularUser();

        $response = $this->getJson(self::buildUri());
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0);
    }

    public function testInitialBidStrategyWithDefault(): void
    {
        $this->setupApiRegularUser();

        $response = $this->getJson(self::buildUri() . '&attach-default=true');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::STRUCTURE_CHECK);
        $response->assertJsonCount(1);
    }

    public function testAddBidStrategy(): void
    {
        $this->setupApiRegularUser();

        $responsePut = $this->putJson(self::buildUri(), self::DATA);
        $responsePut->assertStatus(Response::HTTP_CREATED);

        $responseGet = $this->getJson(self::buildUri());
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
        $user = $this->setupApiRegularUser();
        BidStrategy::factory()->times($limit)->create(['user_id' => $user->id]);

        $responsePut = $this->putJson(self::buildUri(), self::DATA);
        $responsePut->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testEditOwnBidStrategy(): void
    {
        $user = $this->setupApiRegularUser();

        $bidStrategy = BidStrategy::register('test', $user->id, 'web', null);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patchJson(self::buildUriPatchBidStrategy($bidStrategyPublicId), self::DATA);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $bidStrategyEdited = BidStrategy::fetchByPublicId($bidStrategyPublicId)->toArray();
        self::assertEquals(self::DATA['name'], $bidStrategyEdited['name']);
        self::assertEquals(self::DATA['details'], $bidStrategyEdited['details']);
    }

    public function testEditAlienBidStrategy(): void
    {
        $user = $this->setupApiRegularUser();

        $bidStrategy = BidStrategy::register('test', $user->id + 1, 'web', null);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patchJson(self::buildUriPatchBidStrategy($bidStrategyPublicId), self::DATA);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testEditNotExistingBidStrategy(): void
    {
        $this->setupApiRegularUser();

        $bidStrategyPublicId = '0123456789abcdef0123456789abcdef';

        $response = $this->patchJson(self::buildUriPatchBidStrategy($bidStrategyPublicId), self::DATA);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testEditInvalidBidStrategy(): void
    {
        $this->setupApiRegularUser();

        $bidStrategyInvalidPublicId = '1000';

        $response = $this->patchJson(self::buildUriPatchBidStrategy($bidStrategyInvalidPublicId), self::DATA);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDbConnectionErrorWhileAddingBidStrategy(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException());
        DB::shouldReceive('rollback')->andReturnUndefined();

        $this->setupApiRegularUser();

        $response = $this->putJson(self::buildUri(), self::DATA);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testDbConnectionErrorWhileEditingBidStrategy(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException());
        DB::shouldReceive('rollback')->andReturnUndefined();

        $user = $this->setupApiRegularUser();

        $bidStrategy = BidStrategy::register('test', $user->id, 'web', null);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patchJson(self::buildUriPatchBidStrategy($bidStrategyPublicId), self::DATA);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @dataProvider invalidBidStrategyDataProvider
     *
     * @param array $data
     */
    public function testAddingBidStrategyInvalid(array $data): void
    {
        $this->setupApiRegularUser();

        $response = $this->putJson(self::buildUri(), $data);

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
                            'rank' => 10001,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testGetDefaultUuid(): void
    {
        $this->setupApiRegularUser();

        $response = $this->getJson(self::buildUriGetDefaultUuid());

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['uuid']);
    }

    public function testChangeDefaultUuidValid(): void
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user, 'api');
        $initialBidStrategy = (new BidStrategy())->first();
        self::assertTrue($initialBidStrategy->is_default);
        $bidStrategy = BidStrategy::register('test', BidStrategy::ADMINISTRATOR_ID, 'web', null);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $responsePut = $this->patch(self::buildUriPatchDefaultUuid(), ['uuid' => $bidStrategyPublicId]);
        $responsePut->assertStatus(Response::HTTP_NO_CONTENT);
        self::assertFalse(BidStrategy::fetchByPublicId($initialBidStrategy->uuid)->is_default);
        self::assertTrue(BidStrategy::fetchByPublicId($bidStrategyPublicId)->is_default);

        $responseGet = $this->getJson(self::buildUriGetDefaultUuid());
        $responseGet->assertStatus(Response::HTTP_OK);
        $responseGet->assertJsonStructure(['uuid']);
        self::assertEquals($bidStrategyPublicId, $responseGet->json('uuid'));
    }

    public function testChangeDefaultUuidInvalid(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

        $response = $this->patch(self::buildUriPatchDefaultUuid(), ['uuid' => '1234']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testChangeDefaultUuidNotExisting(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

        $response = $this->patch(self::buildUriPatchDefaultUuid(), ['uuid' => '00000000000000000000000000000000']);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testChangeDefaultUuidInvalidMedium(): void
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', $user->id, 'web', null);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patch(
            self::buildUriPatchDefaultUuid('metaverse', 'cryptovoxels'),
            ['uuid' => $bidStrategyPublicId]
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testChangeDefaultUuidInvalidVendor(): void
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', $user->id, 'metaverse', 'decentraland');
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patch(
            self::buildUriPatchDefaultUuid('metaverse', 'cryptovoxels'),
            ['uuid' => $bidStrategyPublicId]
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testChangeDefaultUuidForbidden(): void
    {
        $user = $this->setupApiRegularUser();
        $bidStrategy = BidStrategy::register('test', $user->id, 'web', null);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patch(self::buildUriPatchDefaultUuid(), ['uuid' => $bidStrategyPublicId]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testChangeDefaultUuidOtherUser(): void
    {
        /** @var User $userAdmin */
        $userAdmin = User::factory()->admin()->create();
        $this->actingAs($userAdmin, 'api');
        /** @var User $userOther */
        $userOther = User::factory()->create();
        $bidStrategy = BidStrategy::register('test', $userOther->id, 'web', null);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patch(self::buildUriPatchDefaultUuid(), ['uuid' => $bidStrategyPublicId]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testChangeDefaultUuidDbConnectionError(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException());
        DB::shouldReceive('rollback')->andReturnUndefined();

        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', BidStrategy::ADMINISTRATOR_ID, 'web', null);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patch(self::buildUriPatchDefaultUuid(), ['uuid' => $bidStrategyPublicId]);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @dataProvider downloadSpreadSheetProvider
     */
    public function testDownloadSpreadsheet(
        string $bidStrategyName,
        string $bidStrategyCategory,
        float $bidStrategyRank
    ): void {

        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register($bidStrategyName, $user->id, 'web', null);
        $bidStrategy->bidStrategyDetails()->save(BidStrategyDetail::create($bidStrategyCategory, $bidStrategyRank));

        $response = $this->get(self::buildUriPostBidStrategySpreadsheet($bidStrategy->uuid));
        $response->assertStatus(Response::HTTP_OK);
        $fileName = 'test.xlsx';
        Storage::put($fileName, $response->streamedContent());
        $fileNameWithPath = Storage::path('') . $fileName;
        $reader = IOFactory::createReaderForFile($fileNameWithPath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($fileNameWithPath);

        $sheets = $spreadsheet->getAllSheets();
        $name = $this->getNameFromSheets($sheets);
        $bidStrategyDetails = $this->getBidStrategyDetailsFromSheets($sheets);
        self::assertEquals($bidStrategyName, $name);
        self::assertCount(1, $bidStrategyDetails);
        self::assertEquals($bidStrategyCategory, $bidStrategyDetails[0]['category']);
        self::assertEquals($bidStrategyRank, $bidStrategyDetails[0]['rank']);

        Storage::delete($fileName);
    }

    public function downloadSpreadSheetProvider(): array
    {
        return [
            'user:country' => ['test', 'user:country:af', 0.3],
            'site:domain' => ['test', 'site:domain:example.com', 0.9],
        ];
    }

    public function testUploadSpreadsheetMissingFile(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', $user->id, 'web', null);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->post(self::buildUriPostBidStrategySpreadsheet($bidStrategyPublicId));
        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function testUploadSpreadsheetInvalidMimeType(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', $user->id, 'web', null);
        $bidStrategyPublicId = $bidStrategy->uuid;
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->post(self::buildUriPostBidStrategySpreadsheet($bidStrategyPublicId), ['file' => $file]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUploadSpreadsheetCorruptedFile(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', $user->id, 'web', null);
        $bidStrategyPublicId = $bidStrategy->uuid;
        $file = new UploadedFile(base_path('tests/mock/Files/Banners/empty.zip'), 'empty.zip');

        $response = $this->post(self::buildUriPostBidStrategySpreadsheet($bidStrategyPublicId), ['file' => $file]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUploadSpreadsheetDbConnectionError(): void
    {
        $bidStrategyName = 'test-bis';
        $bidStrategyCategory = 'user:country:af';
        $bidStrategyRank = 0.3;
        $bidStrategyData = [[$bidStrategyCategory, $bidStrategyRank * 100]];

        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', $user->id, 'web', null);
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

        $response = $this->post(self::buildUriPostBidStrategySpreadsheet($bidStrategyPublicId), ['file' => $file]);
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
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register($bidStrategyName, $user->id, 'web', null);
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

        $response = $this->post(self::buildUriPostBidStrategySpreadsheet($bidStrategyPublicId), ['file' => $file]);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $updatedBidStrategy = BidStrategy::fetchByPublicId($bidStrategyPublicId);
        $updatedBidStrategyDetails = $updatedBidStrategy->bidStrategyDetails;
        self::assertEquals($bidStrategyName, $updatedBidStrategy->name);
        self::assertCount(1, $updatedBidStrategyDetails);
        self::assertEquals($bidStrategyCategory, $updatedBidStrategyDetails->first()->category);
        self::assertEquals($bidStrategyRank, $updatedBidStrategyDetails->first()->rank);

        Storage::delete($fileName);
    }

    public function testDelete(): void
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', BidStrategy::ADMINISTRATOR_ID, 'web', null);

        $response = $this->delete(self::buildUriPatchBidStrategy($bidStrategy->uuid));

        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testDeleteOnDbConnectionError(): void
    {
        DB::shouldReceive('selectOne')->andReturnNull();
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException());
        DB::shouldReceive('rollback')->andReturnUndefined();
        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', BidStrategy::ADMINISTRATOR_ID, 'web', null);

        $response = $this->delete(self::buildUriPatchBidStrategy($bidStrategy->uuid));

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testDeleteUsed(): void
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', BidStrategy::ADMINISTRATOR_ID, 'web', null);
        Campaign::factory()->create(['user_id' => $user->id, 'bid_strategy_uuid' => $bidStrategy->uuid]);

        $response = $this->delete(self::buildUriPatchBidStrategy($bidStrategy->uuid));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDeleteDefault(): void
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user, 'api');
        $bidStrategy = (new BidStrategy())->first();

        $response = $this->delete(self::buildUriPatchBidStrategy($bidStrategy->uuid));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
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
            ['Prefix', 'ID', 'Description', 'Value [%]'],
        ];

        foreach ($bidStrategyData as $entry) {
            $id_parts = explode(":", $entry[0]);
            $id = array_pop($id_parts);
            $prefix = implode(":", $id_parts);
            $data[] = [$prefix, $id, '', $entry[1]];
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

    private function getNameFromSheets(array $sheets): string
    {
        $mainSheet = $sheets[0];
        return $mainSheet->getCellByColumnAndRow(2, 1)->getValue();
    }

    private function getBidStrategyDetailsFromSheets(array $sheets): array
    {
        $bidStrategyDetails = [];
        for ($i = 1; $i < count($sheets); $i++) {
            $sheet = $sheets[$i];
            $row = 2;

            while (null !== ($value = $sheet->getCellByColumnAndRow(4, $row)->getValue())) {
                if (100 !== $value) {
                    $category = $sheet->getCellByColumnAndRow(1, $row)->getValue()
                        . ':' . $sheet->getCellByColumnAndRow(2, $row)->getValue();
                    $bidStrategyDetails[] = [
                        'category' => $category,
                        'rank' => (float)$value / 100,
                    ];
                }

                ++$row;
            }
        }
        return $bidStrategyDetails;
    }

    private static function buildUri(string $medium = 'web', ?string $vendor = null): string
    {
        return sprintf('%s/media/%s?vendor=%s', self::URI, $medium, $vendor);
    }

    private static function buildUriPatchBidStrategy(string $uuid): string
    {
        return sprintf('%s/%s', self::URI, $uuid);
    }

    private static function buildUriPostBidStrategySpreadsheet(string $uuid): string
    {
        return sprintf('%s/%s/spreadsheet', self::URI, $uuid);
    }

    private static function buildUriPatchDefaultUuid(string $medium = 'web', ?string $vendor = null): string
    {
        return sprintf('/admin/campaigns/bid-strategy/media/%s/uuid-default?vendor=%s', $medium, $vendor);
    }

    private static function buildUriGetDefaultUuid(string $medium = 'web', ?string $vendor = null): string
    {
        return sprintf('/api/campaigns/bid-strategy/media/%s/uuid-default?vendor=%s', $medium, $vendor);
    }

    private function setupApiRegularUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(ConfigurationRepository::class, new DummyConfigurationRepository());
    }
}
