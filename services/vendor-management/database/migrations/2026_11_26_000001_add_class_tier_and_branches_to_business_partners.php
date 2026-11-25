<?php

declare(strict_types=1);

use App\Models\BusinessPartner;
use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_partners', function (Blueprint $table): void {
            if (!Schema::hasColumn('business_partners', 'class_tier')) {
                $table->string('class_tier', 20)->default('Class C')->after('region')->index();
            }
            if (!Schema::hasColumn('business_partners', 'prs_institution_id')) {
                $table->unsignedBigInteger('prs_institution_id')->nullable()->unique()->after('bp_code');
            }
        });

        if (!Schema::hasTable('partner_branches')) {
            Schema::create('partner_branches', function (Blueprint $table): void {
                $table->id('branch_id');
                $table->string('location_uid', 64)->unique();
                $table->unsignedBigInteger('partner_id')->index();
                $table->unsignedBigInteger('prs_branch_id')->nullable()->unique();
                $table->string('branch_name', 255);
                $table->longText('address');
                $table->string('region', 100)->nullable();
                $table->string('sbsi_branch', 100)->nullable()->index();
                $table->boolean('is_primary')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('partner_id')
                    ->references('partner_id')
                    ->on('business_partners')
                    ->onDelete('cascade');
            });
        }

        // Backfill missing addresses on existing rows via Eloquent so EncryptedCast applies.
        BusinessPartner::query()->orderBy('partner_id')->each(function (BusinessPartner $partner): void {
            $dirty = false;
            if (trim((string) $partner->address) === '') {
                $partner->address = 'Address pending update, Metro Manila, Philippines';
                $dirty = true;
            }
            if (trim((string) $partner->class_tier) === '') {
                $partner->class_tier = 'Class C';
                $dirty = true;
            }
            if ($dirty) {
                $partner->save();
            }

            if (!DB::table('partner_branches')->where('partner_id', $partner->partner_id)->exists()) {
                DB::table('partner_branches')->insert([
                    'location_uid' => 'LOC-' . str_pad((string) $partner->partner_id, 4, '0', STR_PAD_LEFT),
                    'partner_id'   => $partner->partner_id,
                    'branch_name'  => 'Main Branch',
                    'address'      => $partner->getRawOriginal('address'),
                    'region'       => $partner->region,
                    'sbsi_branch'  => 'Main Office',
                    'is_primary'   => true,
                    'is_active'    => ($partner->status ?? 'Active') === 'Active',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        });

        Supplier::query()->orderBy('supplier_id')->each(function (Supplier $supplier): void {
            if (trim((string) $supplier->address) === '') {
                $supplier->address = 'Address pending update, Metro Manila, Philippines';
                $supplier->save();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_branches');

        Schema::table('business_partners', function (Blueprint $table): void {
            if (Schema::hasColumn('business_partners', 'prs_institution_id')) {
                $table->dropUnique(['prs_institution_id']);
                $table->dropColumn('prs_institution_id');
            }
            if (Schema::hasColumn('business_partners', 'class_tier')) {
                $table->dropIndex(['class_tier']);
                $table->dropColumn('class_tier');
            }
        });
    }
};
