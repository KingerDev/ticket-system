<?php

namespace Tests\Feature;

use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/** Export zoznamu hostí do PDF a Excelu. */
class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hall();
        $this->actingAs($this->admin());

        $table = Table::first();
        $this->reservation([
            ['name' => 'Jana Nováková', 'table_id' => $table->id, 'seat_number' => 1, 'allergen_ids' => [1], 'ticket_code' => '001'],
            ['name' => 'Peter Učiteľ', 'is_teacher' => true, 'table_id' => $table->id, 'seat_number' => 2],
            ['name' => 'Eva Bezmiesta'],
        ]);
    }

    public static function kombinacie(): array
    {
        return [
            'PDF podľa mena'          => ['pdf', 'name', false],
            'PDF podľa stola'         => ['pdf', 'table', false],
            'PDF s oddelenými učiteľmi' => ['pdf', 'name', true],
            'Excel podľa mena'        => ['excel', 'name', false],
            'Excel podľa stola'       => ['excel', 'table', false],
            'Excel s oddelenými učiteľmi' => ['excel', 'table', true],
        ];
    }

    #[DataProvider('kombinacie')]
    public function test_export_prejde(string $format, string $sortBy, bool $separateTeachers): void
    {
        $response = $this->get(route('admin.export.download', [
            'format'            => $format,
            'sort_by'           => $sortBy,
            'separate_teachers' => $separateTeachers,
            'include_allergens' => true,
            'include_seat'      => true,
            'include_ticket'    => true,
        ]));

        $response->assertOk();

        // PDF chodí ako bežná odpoveď, Excel ako streamovaná.
        $content = $response->baseResponse instanceof StreamedResponse
            ? $response->streamedContent()
            : $response->getContent();

        $this->assertNotEmpty($content);
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_neplatny_format_neprejde(): void
    {
        $this->get(route('admin.export.download', ['format' => 'docx', 'sort_by' => 'name']))
            ->assertSessionHasErrors('format');
    }

    public function test_neplatne_radenie_neprejde(): void
    {
        $this->get(route('admin.export.download', ['format' => 'pdf', 'sort_by' => 'nieco']))
            ->assertSessionHasErrors('sort_by');
    }

    public function test_stranka_exportu_sa_zobrazi(): void
    {
        $this->get(route('admin.export'))->assertOk();
    }
}
