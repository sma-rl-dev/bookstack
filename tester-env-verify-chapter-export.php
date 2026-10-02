<?php

include '/app/tester-env-verify.php';

$support = BookStack\Entities\Models\Book::query()->where('name', 'Support Playbooks')->first();
$triage = BookStack\Entities\Models\Chapter::query()->where('name', 'Triage Workflows')->first();
$escalation = BookStack\Entities\Models\Chapter::query()->where('name', 'Escalation Contacts')->first();
$roster = BookStack\Entities\Models\Page::query()->where('name', 'Escalation Roster')->first();
$glossary = BookStack\Entities\Models\Page::query()->where('name', 'Glossary – Acronyms & Escalation Codes')->first();

$chapterChecks = [
    'support_has_two_chapters' => BookStack\Entities\Models\Chapter::query()->where('book_id', $support?->id)->count() === 2,
    'escalation_book_scope' => $escalation?->book_id === $support?->id,
    'escalation_slug' => $escalation?->slug === 'escalation-contacts',
    'escalation_url' => str_ends_with($escalation?->getUrl() ?? '', '/books/support-playbooks/chapter/escalation-contacts'),
    'roster_scope' => $roster?->book_id === $support?->id && $roster?->chapter_id === $escalation?->id,
    'roster_slug' => $roster?->slug === 'escalation-roster',
    'roster_url' => str_ends_with($roster?->getUrl() ?? '', '/books/support-playbooks/page/escalation-roster'),
    'roster_contacts' => str_contains($roster?->text ?? '', 'Ari Patel') && str_contains($roster?->text ?? '', 'Mina Chen'),
    'roster_no_bluebird' => stripos($roster?->text ?? 'bluebird', 'bluebird') === false,
    'triage_page_count' => BookStack\Entities\Models\Page::query()->where('chapter_id', $triage?->id)->count() === 2,
    'escalation_page_count' => BookStack\Entities\Models\Page::query()->where('chapter_id', $escalation?->id)->count() === 1,
    'glossary_still_standalone' => $glossary?->chapter_id === null && $glossary?->book_id === $support?->id,
    'search_target_still_single' => BookStack\Entities\Models\Page::query()->where('text', 'like', '%bluebird escalation%')->count() === 1,
    'chapter_route_lookup' => app(BookStack\Entities\Queries\ChapterQueries::class)->findVisibleBySlugsOrFail('support-playbooks', 'escalation-contacts')->id === $escalation->id
        && app(BookStack\Entities\Queries\PageQueries::class)->findVisibleBySlugsOrFail('support-playbooks', 'escalation-roster')->id === $roster->id,
];

foreach ($chapterChecks as $name => $ok) {
    if (!$ok) {
        fwrite(STDERR, "Chapter-export seed verify failed: {$name}\n");
        exit(1);
    }
}

echo "Chapter-export seed verify passed: Support Playbooks has Triage Workflows + Escalation Contacts, Escalation Roster scoped, slugs/routes valid, search target uncontaminated.\n";
