<?php

namespace Tests\Feature;

use App\Enums\DeletionReason;
use App\Events\StoredFileDeleted;
use App\Models\StoredFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeExpiredFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filestorage.disk'));
        Event::fake([StoredFileDeleted::class]);
    }

    public function test_only_expired_files_are_deleted(): void
    {
        $disk = Storage::disk(config('filestorage.disk'));
        $expired = StoredFile::factory()->expired()->create();
        $fresh = StoredFile::factory()->create();
        $disk->put($expired->path, 'old');
        $disk->put($fresh->path, 'new');

        $this->artisan('files:purge-expired')
            ->expectsOutput('Expired files deleted: 1')
            ->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelExists($fresh);
        $disk->assertMissing($expired->path);
        $disk->assertExists($fresh->path);
        Event::assertDispatchedTimes(StoredFileDeleted::class, 1);
        Event::assertDispatched(StoredFileDeleted::class, fn (StoredFileDeleted $event) => $event->file->id === $expired->id
            && $event->file->reason === DeletionReason::Expired);
    }

    public function test_uploaded_file_is_deleted_after_24_hours(): void
    {
        $this->postJson(route('files.store'), ['file' => UploadedFile::fake()->create('report.pdf', 10, 'application/pdf')])
            ->assertCreated();

        $this->travel(23)->hours();
        $this->artisan('files:purge-expired')->assertSuccessful();
        $this->assertDatabaseCount('stored_files', 1);
        Event::assertNotDispatched(StoredFileDeleted::class);

        $this->travel(1)->hours();
        $this->artisan('files:purge-expired')->assertSuccessful();
        $this->assertDatabaseCount('stored_files', 0);
        Event::assertDispatched(StoredFileDeleted::class, fn (StoredFileDeleted $event) => $event->file->originalName === 'report.pdf'
            && $event->file->reason === DeletionReason::Expired);
    }

    public function test_command_handles_many_expired_files(): void
    {
        StoredFile::factory()->expired()->count(150)->create();

        $this->artisan('files:purge-expired')
            ->expectsOutput('Expired files deleted: 150')
            ->assertSuccessful();

        $this->assertDatabaseCount('stored_files', 0);
        Event::assertDispatchedTimes(StoredFileDeleted::class, 150);
    }

    public function test_purge_is_scheduled_every_minute(): void
    {
        Artisan::call('schedule:list');

        $this->assertMatchesRegularExpression('/\* \* \* \* \*\s+php artisan files:purge-expired/', Artisan::output());
    }
}
