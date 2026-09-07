<?php
namespace Tests\Feature;
use App\Models\{Barangay, BarangayRbiUpdate, Household, Inhabitant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CompleteHouseholdRbiTest extends TestCase {
 use RefreshDatabase;
 public function test_complete_household_fields_survive_save_exports_and_registry_addition(): void {
  $b=Barangay::where('name','San Roque')->firstOrFail();$staff=User::factory()->create(['role'=>'barangay','barangay_id'=>$b->id]);
  $house=Household::create(['barangay_id'=>$b->id,'household_number'=>'307']);
  $row=['household_id'=>$house->id,'household_head'=>'Example Parent','household_number'=>'307','last_name'=>'Example','first_name'=>'Child','middle_name'=>'Middle','suffix'=>'Jr.',
   'relationship'=>'Son','complete_address'=>'Purok 2, San Roque','birth_place'=>'Tomas Oppus','birth_date'=>'2010-04-05','recorded_age'=>'16','sex'=>'Male','civil_status'=>'Single','education_level'=>'Grade 10','religion'=>'Catholic','occupation'=>'Student','remarks'=>'Source remark'];
  $this->actingAs($staff)->post(route('barangay.rbi-updates.store'),['reporting_month'=>'2026-09','prepared_by'=>'Encoder Official','certified_by'=>'Secretary Official','attested_by'=>'Captain Official','rows'=>[$row]])->assertSessionHasNoErrors();
  $report=BarangayRbiUpdate::sole();
  foreach(['last_name','first_name','middle_name','suffix','complete_address','recorded_age','education_level','religion','remarks','household_number'] as $field)$this->assertSame($row[$field],$report->rows[0][$field]);
  $this->assertSame('Catholic',$report->rbiFamilies->first()->members->first()->details['religion']);
  $this->get(route('rbi-updates.show',$report))->assertOk()->assertSee('307')->assertSee('Purok 2, San Roque')->assertSee('Grade 10')->assertSee('Catholic')->assertSee('Certified Correct')->assertSee('Secretary Official');
  $word=$this->get(route('rbi-updates.export-word',$report))->assertOk();$path=tempnam(sys_get_temp_dir(),'full-rbi-');
  try {file_put_contents($path,$word->streamedContent());$zip=new \ZipArchive();$zip->open($path);$xml=$zip->getFromName('word/document.xml');$zip->close();
   $this->assertNotFalse(simplexml_load_string($xml));foreach(['307','Purok 2, San Roque','Grade 10','Catholic','Source remark','Certified Correct','Secretary Official'] as $text)$this->assertStringContainsString($text,$xml);
  }finally{unlink($path);}
  $pdf=$this->get(route('rbi-updates.export-pdf',$report))->assertOk();$this->assertStringStartsWith('%PDF',$pdf->getContent());
  $this->post(route('barangay.rbi-updates.add-to-registry',$report))->assertSessionHasNoErrors();
  $person=Inhabitant::sole();foreach(['complete_address','education_level','religion','suffix'] as $field)$this->assertSame($row[$field],$person->$field);
  $this->assertSame(16,$person->recorded_age);$this->assertStringContainsString('Source remark',$person->remarks);$this->assertSame($house->id,$person->household_id);
 }
}
