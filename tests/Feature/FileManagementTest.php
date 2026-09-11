<?php

namespace Tests\Feature;

use App\Contracts\FileRemover;
use App\Enums\DeletionReason;
use App\Events\StoredFileDeleted;
use App\Models\StoredFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileManagementTest extends TestCase
{
    use RefreshDatabase;

    private Filesystem $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = Storage::fake(config('filestorage.disk'));
        Event::fake([StoredFileDeleted::class]);
    }

    public function test_index_lists_uploaded_files(): void
    {
        StoredFile::factory()->create(['original_name' => 'contract.pdf']);
        StoredFile::factory()->create(['original_name' => 'invoice.docx']);

        $this->get(route('files.index'))
            ->assertOk()
            ->assertSee('contract.pdf')
            ->assertSee('invoice.docx');
    }

    public function test_index_shows_empty_state(): void
    {
        $this->get(route('files.index'))
            ->assertOk()
            ->assertSee('Файлов пока нет');
    }

    public function test_file_is_downloaded_with_original_name(): void
    {
        $file = $this->storedFile('contract.pdf');

        $this->get(route('files.download', $file))
            ->assertOk()
            ->assertDownload('contract.pdf');
    }

    public function test_download_of_file_missing_on_disk_returns_404(): void
    {
        $file = StoredFile::factory()->create();

        $this->get(route('files.download', $file))->assertNotFound();
    }

    public function test_file_is_deleted_manually_and_deletion_is_announced(): void
    {
        $file = $this->storedFile('contract.pdf');

        $this->deleteJson(route('files.destroy', $file))->assertNoContent();

        $this->assertModelMissing($file);
        $this->disk->assertMissing($file->path);
        Event::assertDispatched(StoredFileDeleted::class, fn (StoredFileDeleted $event) => $event->file->id === $file->id
            && $event->file->originalName === 'contract.pdf'
            && $event->file->reason === DeletionReason::Manual);
    }

    public function test_delete_form_without_javascript_redirects_with_message(): void
    {
        $file = $this->storedFile('contract.pdf');

        $this->delete(route('files.destroy', $file))
            ->assertRedirect(route('files.index'))
            ->assertSessionHas('status', 'Файл «contract.pdf» удалён.');

        $this->assertModelMissing($file);
    }

    public function test_deleting_unknown_file_returns_404(): void
    {
        $this->deleteJson(route('files.destroy', 999))->assertNotFound();

        Event::assertNotDispatched(StoredFileDeleted::class);
    }

    public function test_file_deleted_twice_is_announced_once(): void
    {
        $file = $this->storedFile('contract.pdf');
        $remover = $this->app->make(FileRemover::class);

        $this->assertTrue($remover->delete($file, DeletionReason::Manual));
        $this->assertFalse($remover->delete($file, DeletionReason::Expired));

        Event::assertDispatchedTimes(StoredFileDeleted::class, 1);
    }

    public function test_record_is_removed_even_if_file_is_already_missing_on_disk(): void
    {
        $file = StoredFile::factory()->create();

        $this->deleteJson(route('files.destroy', $file))->assertNoContent();

        $this->assertModelMissing($file);
        Event::assertDispatched(StoredFileDeleted::class);
    }

    private function storedFile(string $name): StoredFile
    {
        $file = StoredFile::factory()->create(['original_name' => $name]);
        $this->disk->put($file->path, '%PDF-1.4 test');

        return $file;
    }
}
