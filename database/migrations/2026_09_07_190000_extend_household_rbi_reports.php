<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('inhabitants',fn(Blueprint $t)=>$t->string('complete_address')->nullable());
  Schema::table('barangay_rbi_updates',function(Blueprint $t){$t->string('certified_by')->nullable();$t->string('certified_signature_path')->nullable();});
  Schema::table('barangay_rbi_members',function(Blueprint $t){$t->json('details')->nullable();});
  Schema::table('barangay_rbi_families',function(Blueprint $t){$t->string('household_number',100)->nullable();});
 }
 public function down(): void {
  Schema::table('inhabitants',fn(Blueprint $t)=>$t->dropColumn('complete_address'));
  Schema::table('barangay_rbi_updates',fn(Blueprint $t)=>$t->dropColumn(['certified_by','certified_signature_path']));
  Schema::table('barangay_rbi_members',fn(Blueprint $t)=>$t->dropColumn('details'));
  Schema::table('barangay_rbi_families',fn(Blueprint $t)=>$t->dropColumn('household_number'));
 }
};
