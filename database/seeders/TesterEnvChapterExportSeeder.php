<?php

namespace Database\Seeders;

use BookStack\Activity\Models\Tag;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Permissions\JointPermissionBuilder;
use BookStack\Search\SearchIndex;
use BookStack\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TesterEnvChapterExportSeeder extends Seeder
{
    private const FIXED_TIME = '2026-02-15 09:30:00';

    public function run(): void
    {
        Carbon::setTestNow(Carbon::parse(self::FIXED_TIME));

        DB::transaction(function () {
            $support = Book::query()->where('name', 'Support Playbooks')->firstOrFail();
            $editor = User::query()->where('email', 'ari.editor@example.test')->firstOrFail();

            $escalation = Chapter::query()
                ->where('book_id', $support->id)
                ->where('name', 'Escalation Contacts')
                ->first();
            if ($escalation === null) {
                $escalation = new Chapter();
                $escalation->forceFill($this->baseEntity('Escalation Contacts', $editor) + [
                    'book_id' => $support->id,
                    'priority' => 2,
                    'description' => 'Who to contact when triage needs a higher tier.',
                    'description_html' => '<p>Who to contact when triage needs a higher tier.</p>',
                ]);
                $escalation->save();
            }

            $roster = Page::query()
                ->where('book_id', $support->id)
                ->where('name', 'Escalation Roster')
                ->first();
            if ($roster === null) {
                $body = 'Primary on-call is Ari Patel (ari.editor@example.test) for urgent pages during business hours. Secondary contact is Mina Chen (mina.viewer@example.test) for customer follow-up. If neither responds within 30 minutes, page the platform owner and note the handoff in the shift log.';
                $roster = new Page();
                $roster->forceFill($this->baseEntity('Escalation Roster', $editor) + [
                    'book_id' => $support->id,
                    'chapter_id' => $escalation->id,
                    'priority' => 1,
                    'draft' => false,
                    'template' => false,
                    'revision_count' => 1,
                    'editor' => 'wysiwyg',
                    'html' => '<p>' . e($body) . '</p>',
                    'text' => $body,
                    'markdown' => '',
                ]);
                $roster->save();
                $roster->tags()->save(new Tag([
                    'name' => 'workflow',
                    'value' => 'escalation',
                    'order' => 0,
                ]));
            }
        });

        app(JointPermissionBuilder::class)->rebuildForAll();
        app(SearchIndex::class)->indexAllEntities();
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
}
