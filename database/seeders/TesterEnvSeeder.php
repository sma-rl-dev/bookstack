<?php

namespace Database\Seeders;

use BookStack\Activity\Models\Tag;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Permissions\JointPermissionBuilder;
use BookStack\Search\SearchIndex;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TesterEnvSeeder extends Seeder
{
    private const FIXED_TIME = '2026-02-15 09:30:00';
    private const PASSWORD_HASH = '$2y$12$abcdefghijklmnopqrstuutwZ1IOTtu3SsEBT5lI/LFncP31tIybm';

    public function run(): void
    {
        Carbon::setTestNow(Carbon::parse(self::FIXED_TIME));

        DB::transaction(function () {
            $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
            $admin->forceFill(['password' => self::PASSWORD_HASH])->save();
            $editor = $this->user('Ari Patel', 'ari.editor@example.test', 'editor');
            $viewer = $this->user('Mina Chen', 'mina.viewer@example.test', 'viewer');

            $opsShelf = $this->shelf('Operations Hub', 'Operational playbooks, escalation paths, and incident response guidance.', $admin, [
                ['domain', 'operations'],
                ['status', 'active'],
            ]);
            $productShelf = $this->shelf('Product Knowledge Base', 'Launch planning, customer research, and product reference notes.', $editor, [
                ['domain', 'product'],
                ['quarter', '2026-Q2'],
            ]);

            $support = $this->book('Support Playbooks', 'Customer support workflows for a small SaaS help desk.', $editor, [
                ['team', 'support'],
                ['status', 'active'],
            ]);
            $launch = $this->book('Product Launch Q2 2026', 'Cross-functional launch plan for the Q2 analytics release.', $editor, [
                ['team', 'product'],
                ['quarter', '2026-Q2'],
            ]);
            $runbooks = $this->book('Engineering Runbooks', 'Service ownership, deployment, and recovery procedures.', $admin, [
                ['team', 'engineering'],
                ['status', 'maintained'],
            ]);
            $research = $this->book('Customer Research Archive', 'Interview notes and synthesized product feedback.', $viewer, [
                ['team', 'research'],
                ['status', 'archived'],
            ]);

            $opsShelf->books()->attach([$support->id, $runbooks->id]);
            $productShelf->books()->attach([$launch->id, $research->id]);

            $supportBasics = $this->chapter($support, 'Triage Workflows', 'How the support team prioritizes and routes requests.', $editor, 1);
            $this->page($support, $supportBasics, 'Priority Matrix', 'Visible seed phrase: bluebird escalation. P0 issues are acknowledged within 15 minutes; P1 issues within one business hour.', $editor, 1, [['workflow', 'triage'], ['sla', '15-minutes']]);
            $this->page($support, $supportBasics, 'Refund Exceptions', 'Use this page when billing disputes require manager approval before a refund is issued.', $editor, 2, [['workflow', 'billing'], ['status', 'active']]);
            $this->page($support, null, 'Glossary – Acronyms & Escalation Codes', 'Reference for internal shorthand including CSS, CSM, EIR, and the BLUEBIRD code word.', $editor, 10, [['reference', 'glossary']]);

            $launchPlan = $this->chapter($launch, 'Launch Readiness', 'Milestones and owners for the Q2 analytics launch.', $editor, 1);
            $this->page($launch, $launchPlan, 'Go-To-Market Checklist', 'Checklist includes pricing review, enablement deck, partner briefing, and launch-day support rotation.', $editor, 1, [['status', 'draft-ready'], ['owner', 'product']]);
            $this->page($launch, $launchPlan, 'Beta Feedback Summary', 'Top beta themes: onboarding friction, cohort comparison demand, and export formatting improvements.', $editor, 2, [['source', 'beta'], ['status', 'reviewed']]);
            $launchMessaging = $this->chapter($launch, 'Messaging Library', 'Reusable launch copy for public and internal channels.', $editor, 2);
            $this->page($launch, $launchMessaging, 'Customer Email Draft', 'Email draft for workspace administrators announcing saved segments and dashboard annotations.', $editor, 1, [['audience', 'customers'], ['status', 'draft']]);

            $incident = $this->chapter($runbooks, 'Incident Response', 'Procedures for responding to production incidents.', $admin, 1);
            $this->page($runbooks, $incident, 'API Latency Runbook', 'If p95 latency exceeds 850ms for ten minutes, page the platform owner and scale queue workers.', $admin, 1, [['service', 'api'], ['severity', 'high']]);
            $this->page($runbooks, $incident, 'Database Failover Steps', 'Validate replica lag, announce read-only mode, promote the warm standby, then reopen writes.', $admin, 2, [['service', 'database'], ['severity', 'critical']]);
            $deploy = $this->chapter($runbooks, 'Deployment Notes', 'Release process and rollback references.', $admin, 2);
            $this->page($runbooks, $deploy, 'Weekly Release Checklist', 'Every Thursday release requires changelog review, smoke tests, and rollback link verification.', $admin, 1, [['workflow', 'release'], ['cadence', 'weekly']]);

            $interviews = $this->chapter($research, 'Interview Summaries', 'Selected customer interviews kept for product planning.', $viewer, 1);
            $this->page($research, $interviews, 'Acme Corp Admin Interview', 'Acme needs clearer audit exports and asked for saved filters by role.', $viewer, 1, [['customer', 'Acme Corp'], ['status', 'archived']]);
            $this->page($research, $interviews, 'Northwind Support Lead Interview', 'Northwind requested better bulk assignment flows and fewer confirmation dialogs.', $viewer, 2, [['customer', 'Northwind'], ['status', 'archived']]);
            $this->page($research, null, 'Research Backlog Parking Lot', 'Future questions: mobile approvals, knowledge base freshness scoring, and import mapping.', $viewer, 10, [['status', 'parking-lot']]);
        });

        app(JointPermissionBuilder::class)->rebuildForAll();
        app(SearchIndex::class)->indexAllEntities();
    }

    private function user(string $name, string $email, string $roleName): User
    {
        $user = new User();
        $user->forceFill([
            'name' => $name,
            'slug' => Str::slug($name),
            'email' => $email,
            'password' => self::PASSWORD_HASH,
            'email_confirmed' => true,
            'created_at' => self::FIXED_TIME,
            'updated_at' => self::FIXED_TIME,
        ]);
        $user->save();
        $user->roles()->sync([Role::getRole($roleName)->id]);

        return $user;
    }

    private function shelf(string $name, string $description, User $owner, array $tags): Bookshelf
    {
        $shelf = new Bookshelf();
        $shelf->forceFill($this->baseEntity($name, $owner) + [
            'description' => $description,
            'description_html' => '<p>' . e($description) . '</p>',
        ]);
        $shelf->save();
        $this->tags($shelf, $tags);

        return $shelf;
    }

    private function book(string $name, string $description, User $owner, array $tags): Book
    {
        $book = new Book();
        $book->forceFill($this->baseEntity($name, $owner) + [
            'description' => $description,
            'description_html' => '<p>' . e($description) . '</p>',
        ]);
        $book->save();
        $this->tags($book, $tags);

        return $book;
    }

    private function chapter(Book $book, string $name, string $description, User $owner, int $priority): Chapter
    {
        $chapter = new Chapter();
        $chapter->forceFill($this->baseEntity($name, $owner) + [
            'book_id' => $book->id,
            'priority' => $priority,
            'description' => $description,
            'description_html' => '<p>' . e($description) . '</p>',
        ]);
        $chapter->save();

        return $chapter;
    }

    private function page(Book $book, ?Chapter $chapter, string $name, string $body, User $owner, int $priority, array $tags): Page
    {
        $page = new Page();
        $html = '<p>' . e($body) . '</p>';
        $page->forceFill($this->baseEntity($name, $owner) + [
            'book_id' => $book->id,
            'chapter_id' => $chapter?->id,
            'priority' => $priority,
            'draft' => false,
            'template' => false,
            'revision_count' => 1,
            'editor' => 'wysiwyg',
            'html' => $html,
            'text' => $body,
            'markdown' => '',
        ]);
        $page->save();
        $this->tags($page, $tags);

        return $page;
    }

    private function baseEntity(string $name, User $owner): array
    {
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'owned_by' => $owner->id,
            'created_at' => self::FIXED_TIME,
            'updated_at' => self::FIXED_TIME,
        ];
    }

    private function tags($entity, array $tags): void
    {
        foreach (array_values($tags) as $index => [$name, $value]) {
            $entity->tags()->save(new Tag([
                'name' => $name,
                'value' => $value,
                'order' => $index,
            ]));
        }
    }
}
