<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\CommercialProspection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CommercialProspectionTest extends TestCase
{
    use RefreshDatabase;

    private function commercial(): User
    {
        return User::factory()->create(['role_key' => 'commercial']);
    }

    // ── Commercial : création ──────────────────────────────────────────

    public function test_a_commercial_can_create_a_prospection_with_text_only(): void
    {
        $user = $this->commercial();

        $response = $this->actingAs($user)->post(route('commercial.prospections.store'), [
            'title' => 'Semaine 32',
            'content' => 'Compte rendu de la semaine.',
        ]);

        $response->assertRedirect(route('commercial.prospections.index'));
        $this->assertDatabaseHas('commercial_prospections', [
            'commercial_id' => $user->id,
            'title' => 'Semaine 32',
            'status' => CommercialProspection::STATUS_DRAFT,
        ]);
    }

    public function test_a_commercial_can_create_a_prospection_with_file_only(): void
    {
        $user = $this->commercial();
        $file = UploadedFile::fake()->create('rapport.pdf', 200, 'application/pdf');

        $response = $this->actingAs($user)->post(route('commercial.prospections.store'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('commercial.prospections.index'));
        $this->assertDatabaseHas('commercial_prospections', [
            'commercial_id' => $user->id,
            'file_name' => 'rapport.pdf',
        ]);
    }

    public function test_a_commercial_can_create_a_prospection_with_text_and_file(): void
    {
        $user = $this->commercial();
        $file = UploadedFile::fake()->create('rapport.xlsx', 100, 'application/vnd.ms-excel');

        $response = $this->actingAs($user)->post(route('commercial.prospections.store'), [
            'content' => 'Résumé de la prospection.',
            'file' => $file,
        ]);

        $response->assertRedirect(route('commercial.prospections.index'));
        $this->assertDatabaseHas('commercial_prospections', [
            'commercial_id' => $user->id,
            'content' => 'Résumé de la prospection.',
            'file_name' => 'rapport.xlsx',
        ]);
    }

    public function test_an_empty_prospection_is_rejected(): void
    {
        $user = $this->commercial();

        $response = $this->actingAs($user)->post(route('commercial.prospections.store'), [
            'title' => 'Titre seul, sans contenu ni fichier',
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('commercial_prospections', ['title' => 'Titre seul, sans contenu ni fichier']);
    }

    public function test_a_dangerous_file_extension_is_rejected(): void
    {
        $user = $this->commercial();
        $file = UploadedFile::fake()->create('malicious.php', 10, 'application/x-php');

        $response = $this->actingAs($user)->post(route('commercial.prospections.store'), [
            'content' => 'texte',
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('commercial_prospections', 0);
    }

    public function test_a_file_that_is_too_large_is_rejected(): void
    {
        $user = $this->commercial();
        $file = UploadedFile::fake()->create('rapport.pdf', 25000, 'application/pdf'); // 25 Mo > 20 Mo max

        $response = $this->actingAs($user)->post(route('commercial.prospections.store'), [
            'content' => 'texte',
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
    }

    // ── Commercial : cycle de vie ───────────────────────────────────────

    public function test_a_commercial_can_edit_their_own_draft(): void
    {
        $user = $this->commercial();
        $prospection = CommercialProspection::create([
            'commercial_id' => $user->id,
            'content' => 'Version initiale',
            'status' => CommercialProspection::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user)->put(route('commercial.prospections.update', $prospection), [
            'content' => 'Version modifiée',
        ]);

        $response->assertRedirect(route('commercial.prospections.index'));
        $this->assertSame('Version modifiée', $prospection->fresh()->content);
    }

    public function test_submitting_a_prospection_notifies_all_platform_admins(): void
    {
        $user = $this->commercial();
        $admin1 = User::factory()->create(['is_platform_admin' => true]);
        $admin2 = User::factory()->create(['is_platform_admin' => true]);
        $prospection = CommercialProspection::create([
            'commercial_id' => $user->id,
            'content' => 'À envoyer',
            'status' => CommercialProspection::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user)->post(route('commercial.prospections.submit', $prospection));

        $response->assertRedirect(route('commercial.prospections.index'));
        $this->assertSame(CommercialProspection::STATUS_SUBMITTED, $prospection->fresh()->status);
        $this->assertNotNull($prospection->fresh()->submitted_at);
        $this->assertSame(2, AppNotification::where('type', 'info')->count());
        $this->assertDatabaseHas('app_notifications', ['user_id' => $admin1->id]);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $admin2->id]);
    }

    public function test_a_commercial_can_view_their_own_prospection(): void
    {
        $user = $this->commercial();
        $prospection = CommercialProspection::create([
            'commercial_id' => $user->id,
            'content' => 'Contenu',
            'status' => CommercialProspection::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user)->get(route('commercial.prospections.show', $prospection));

        $response->assertOk();
    }

    public function test_a_commercial_cannot_view_another_commercials_prospection(): void
    {
        $userA = $this->commercial();
        $userB = $this->commercial();
        $prospection = CommercialProspection::create([
            'commercial_id' => $userB->id,
            'content' => 'Privé à B',
            'status' => CommercialProspection::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($userA)->get(route('commercial.prospections.show', $prospection));

        $response->assertForbidden();
    }

    public function test_a_commercial_cannot_edit_another_commercials_prospection(): void
    {
        $userA = $this->commercial();
        $userB = $this->commercial();
        $prospection = CommercialProspection::create([
            'commercial_id' => $userB->id,
            'content' => 'Privé à B',
            'status' => CommercialProspection::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($userA)->put(route('commercial.prospections.update', $prospection), [
            'content' => 'Tentative de modification',
        ]);

        $response->assertForbidden();
        $this->assertSame('Privé à B', $prospection->fresh()->content);
    }

    public function test_a_commercial_cannot_delete_another_commercials_prospection(): void
    {
        $userA = $this->commercial();
        $userB = $this->commercial();
        $prospection = CommercialProspection::create([
            'commercial_id' => $userB->id,
            'content' => 'Privé à B',
            'status' => CommercialProspection::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($userA)->delete(route('commercial.prospections.destroy', $prospection));

        $response->assertForbidden();
        $this->assertDatabaseHas('commercial_prospections', ['id' => $prospection->id]);
    }

    public function test_a_submitted_prospection_can_no_longer_be_freely_edited(): void
    {
        $user = $this->commercial();
        $prospection = CommercialProspection::create([
            'commercial_id' => $user->id,
            'content' => 'Envoyée',
            'status' => CommercialProspection::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('commercial.prospections.update', $prospection), [
            'content' => 'Tentative',
        ]);

        $response->assertForbidden();
    }

    public function test_a_prospection_returned_for_revision_can_be_edited_and_resubmitted(): void
    {
        $user = $this->commercial();
        $prospection = CommercialProspection::create([
            'commercial_id' => $user->id,
            'content' => 'À corriger',
            'status' => CommercialProspection::STATUS_NEEDS_REVISION,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('commercial.prospections.update', $prospection), [
            'content' => 'Corrigé',
            'submit_now' => '1',
        ]);

        $response->assertRedirect(route('commercial.prospections.index'));
        $this->assertSame(CommercialProspection::STATUS_SUBMITTED, $prospection->fresh()->status);
        $this->assertSame('Corrigé', $prospection->fresh()->content);
    }

    // ── Administration ──────────────────────────────────────────────────

    public function test_admin_can_see_all_prospections(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $userA = $this->commercial();
        $userB = $this->commercial();
        CommercialProspection::create(['commercial_id' => $userA->id, 'content' => 'A', 'status' => CommercialProspection::STATUS_SUBMITTED, 'submitted_at' => now()]);
        CommercialProspection::create(['commercial_id' => $userB->id, 'content' => 'B', 'status' => CommercialProspection::STATUS_SUBMITTED, 'submitted_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.prospections.index'));

        $response->assertOk();
        $response->assertSee($userA->name);
        $response->assertSee($userB->name);
        $this->assertSame(2, CommercialProspection::count());
    }

    public function test_admin_can_view_a_single_prospection(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $user = $this->commercial();
        $prospection = CommercialProspection::create([
            'commercial_id' => $user->id,
            'content' => 'Contenu détaillé',
            'status' => CommercialProspection::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.prospections.show', $prospection));

        $response->assertOk();
        $response->assertSee('Contenu détaillé');
    }

    public function test_admin_can_approve_a_prospection_and_notify_the_commercial(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $user = $this->commercial();
        $prospection = CommercialProspection::create([
            'commercial_id' => $user->id,
            'content' => 'Contenu',
            'status' => CommercialProspection::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.prospections.approve', $prospection), [
            'admin_comment' => 'Bon travail',
        ]);

        $response->assertRedirect(route('admin.prospections.index'));
        $prospection->refresh();
        $this->assertSame(CommercialProspection::STATUS_APPROVED, $prospection->status);
        $this->assertSame($admin->id, $prospection->reviewed_by);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $user->id]);
    }

    public function test_admin_can_request_a_revision(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $user = $this->commercial();
        $prospection = CommercialProspection::create([
            'commercial_id' => $user->id,
            'content' => 'Contenu',
            'status' => CommercialProspection::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.prospections.request-revision', $prospection), [
            'admin_comment' => 'Précisez les montants',
        ]);

        $response->assertRedirect(route('admin.prospections.index'));
        $this->assertSame(CommercialProspection::STATUS_NEEDS_REVISION, $prospection->fresh()->status);
    }

    public function test_admin_can_reject_a_prospection(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $user = $this->commercial();
        $prospection = CommercialProspection::create([
            'commercial_id' => $user->id,
            'content' => 'Contenu',
            'status' => CommercialProspection::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.prospections.reject', $prospection));

        $response->assertRedirect(route('admin.prospections.index'));
        $this->assertSame(CommercialProspection::STATUS_REJECTED, $prospection->fresh()->status);
    }

    public function test_a_non_admin_cannot_access_the_admin_prospections_area(): void
    {
        $user = $this->commercial();

        $response = $this->actingAs($user)->get(route('admin.prospections.index'));

        $response->assertForbidden();
    }
}
