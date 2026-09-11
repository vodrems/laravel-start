<?php

namespace Tests\Feature;

use App\Models\StoredFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filestorage.disk'));
    }

    public function test_upload_page_is_displayed(): void
    {
        $this->get(route('files.create'))
            ->assertOk()
            ->assertSee('Загрузка файлов')
            ->assertSee(route('files.store'));
    }

    #[DataProvider('allowedFiles')]
    public function test_pdf_and_docx_files_are_uploaded(string $name, string $mime): void
    {
        $this->freezeSecond();

        $this->postJson(route('files.store'), ['file' => UploadedFile::fake()->create($name, 512, $mime)])
            ->assertCreated()
            ->assertJsonPath('data.name', $name)
            ->assertJsonPath('data.size', 512 * 1024);

        $file = StoredFile::sole();
        $this->assertSame($name, $file->original_name);
        $this->assertSame($mime, $file->mime_type);
        $this->assertTrue($file->expires_at->equalTo(now()->addHours(24)));
        Storage::disk(config('filestorage.disk'))->assertExists($file->path);
    }

    public static function allowedFiles(): array
    {
        return [
            'pdf' => ['report.pdf', 'application/pdf'],
            'docx' => ['letter.docx', self::DOCX_MIME],
        ];
    }

    public function test_retention_period_is_configurable(): void
    {
        $this->freezeSecond();
        config(['filestorage.ttl_hours' => 1]);

        $this->postJson(route('files.store'), ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
            ->assertCreated();

        $this->assertTrue(StoredFile::sole()->expires_at->equalTo(now()->addHour()));
    }

    #[DataProvider('rejectedFiles')]
    public function test_invalid_files_are_rejected(string $name, int $kilobytes, string $mime): void
    {
        $this->postJson(route('files.store'), ['file' => UploadedFile::fake()->create($name, $kilobytes, $mime)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('stored_files', 0);
        $this->assertEmpty(Storage::disk(config('filestorage.disk'))->allFiles());
    }

    public static function rejectedFiles(): array
    {
        return [
            'other type' => ['notes.txt', 10, 'text/plain'],
            'text renamed to pdf' => ['fake.pdf', 10, 'text/plain'],
            'legacy doc' => ['old.doc', 10, 'application/msword'],
            'larger than 10MB' => ['big.pdf', 10241, 'application/pdf'],
        ];
    }

    public function test_file_of_exactly_10mb_is_accepted(): void
    {
        $this->postJson(route('files.store'), ['file' => UploadedFile::fake()->create('max.pdf', 10240, 'application/pdf')])
            ->assertCreated();
    }

    public function test_request_without_file_is_rejected(): void
    {
        $this->postJson(route('files.store'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Выберите файл для загрузки.']);
    }
}
