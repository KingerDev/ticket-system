<?php

namespace Tests\Feature;

use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Rap2hpoutre\FastExcel\FastExcel;
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
            'Word podľa mena'         => ['word', 'name', false],
            'Word podľa stola'        => ['word', 'table', false],
            'Word podľa lístka s oddelenými učiteľmi' => ['word', 'ticket', true],
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

    public function test_export_podla_stola_ide_podla_cisla_stola(): void
    {
        $stol = fn (string $rad, int $pozicia) => Table::where('row_label', $rad)->where('position_in_row', $pozicia)->first();

        // Rad A má čísla 4, 3, 2, 1, preto stôl 1 (pozícia 4) musí byť prvý;
        // stôl 10 zase nesmie skončiť hneď za stolom 1 ako pri radení textu.
        $this->reservation([
            ['name' => 'Tomáš Desiaty', 'table_id' => $stol('C', 3)->id, 'seat_number' => 1],
            ['name' => 'Anna Piata', 'table_id' => $stol('B', 1)->id, 'seat_number' => 1],
            ['name' => 'Oto Prvý', 'table_id' => $stol('A', 4)->id, 'seat_number' => 1],
        ]);

        $response = $this->get(route('admin.export.download', ['format' => 'excel', 'sort_by' => 'table']));

        $subor = tempnam(sys_get_temp_dir(), 'export') . '.xlsx';
        file_put_contents($subor, $response->streamedContent());
        $riadky = (new FastExcel)->import($subor);

        $zip = new \ZipArchive;
        $zip->open($subor);
        preg_match_all('/<row r="(\d+)"/', $zip->getFromName('xl/worksheets/sheet1.xml'), $cisla);
        $zip->close();
        unlink($subor);

        $this->assertSame(
            ['1', '4', '4', '5', '10', '-'],
            $riadky->map(fn ($r) => (string) $r['Stôl'])->all(),
        );

        // Čítačka prázdne riadky zahodí, preto medzery kontrolujeme v samotnom
        // súbore: chýbajúce čísla riadkov sú voľné riadky medzi stolmi.
        $this->assertSame(['1', '2', '4', '5', '7', '9', '11'], $cisla[1]);
    }

    public function test_pdf_oddeli_stoly_prazdnym_riadkom_len_pri_radeni_podla_stola(): void
    {
        $stol = Table::first();
        $hostia = \App\Models\Guest::with(['table', 'registration'])->whereNotNull('table_id')->get();
        $bezMiesta = \App\Models\Guest::with(['table', 'registration'])->whereNull('table_id')->get();
        $zoznam = $hostia->concat($bezMiesta)->values();

        $vykresli = fn (bool $oddelit) => view('exports.guests', [
            'mainList'         => $zoznam,
            'teacherList'      => collect(),
            'includeAllergens' => false,
            'includeSeat'      => true,
            'includeTicket'    => false,
            'separateTables'   => $oddelit,
            'generatedAt'      => 'teraz',
        ])->render();

        $this->assertSame($stol->id, $hostia->first()->table_id);
        $this->assertSame(1, substr_count($vykresli(true), '<tr class="table-gap">'));
        $this->assertSame(0, substr_count($vykresli(false), '<tr class="table-gap">'));
    }

    public function test_export_podla_listka_ide_podla_cisla_a_bez_listka_na_koniec(): void
    {
        $this->reservation([
            ['name' => 'Zdeno Prvý', 'ticket_code' => '000'],
            ['name' => 'Oľga Posledná', 'ticket_code' => '999'],
        ]);

        $riadky = $this->excelRiadky(['format' => 'excel', 'sort_by' => 'ticket']);

        // Stĺpec s lístkom je v exporte aj bez zaškrtnutia možnosti.
        $this->assertSame(
            ['000', '001', '999', '-', '-'],
            $riadky->map(fn ($r) => (string) $r['Č. lístka'])->all(),
        );
    }

    public function test_admin_bez_usadzania_moze_radit_podla_listka_nie_stola(): void
    {
        $this->actingAs($this->admin(['hide_seating' => true]));

        $this->get(route('admin.export.download', ['format' => 'excel', 'sort_by' => 'ticket']))->assertOk();
        $this->get(route('admin.export.download', ['format' => 'excel', 'sort_by' => 'table']))->assertSessionHasErrors('sort_by');
    }

    public function test_word_ma_zoznam_a_zvladne_specialne_znaky(): void
    {
        // Znak & by bez escapovania pokazil XML a Word by súbor neotvoril.
        $this->reservation([['name' => 'Ján O&Neil', 'table_id' => Table::where('position_in_row', 2)->first()->id, 'seat_number' => 1]]);

        $response = $this->get(route('admin.export.download', [
            'format' => 'word', 'sort_by' => 'table', 'separate_teachers' => true,
        ]));

        $subor = tempnam(sys_get_temp_dir(), 'export') . '.docx';
        file_put_contents($subor, $response->streamedContent());
        $zip = new \ZipArchive;
        $zip->open($subor);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($subor);

        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString('Jana Nováková', $xml);
        $this->assertStringContainsString('Ján O&amp;Neil', $xml);
    }

    public function test_excel_prejde_aj_bez_hosti(): void
    {
        \App\Models\Guest::query()->delete();

        $this->get(route('admin.export.download', ['format' => 'excel', 'sort_by' => 'table']))->assertOk();
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

    private function excelRiadky(array $parametre)
    {
        $subor = tempnam(sys_get_temp_dir(), 'export') . '.xlsx';
        file_put_contents($subor, $this->get(route('admin.export.download', $parametre))->streamedContent());
        $riadky = (new FastExcel)->import($subor);
        unlink($subor);

        return $riadky;
    }

    public function test_stranka_exportu_sa_zobrazi(): void
    {
        $this->get(route('admin.export'))->assertOk();
    }
}
