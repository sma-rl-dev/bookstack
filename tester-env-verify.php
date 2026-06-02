<?php

$admin = BookStack\Users\Models\User::query()->where('email', 'admin@admin.com')->first();
Illuminate\Support\Facades\Auth::login($admin);

$support = BookStack\Entities\Models\Book::query()->where('name', 'Support Playbooks')->first();
$priority = BookStack\Entities\Models\Page::query()->where('name', 'Priority Matrix')->first();
$triage = BookStack\Entities\Models\Chapter::query()->where('name', 'Triage Workflows')->first();

$checks = [
    'shelves' => BookStack\Entities\Models\Bookshelf::query()->whereIn('name', ['Operations Hub', 'Product Knowledge Base'])->count() === 2,
    'books' => BookStack\Entities\Models\Book::query()->whereIn('name', ['Support Playbooks', 'Product Launch Q2 2026', 'Engineering Runbooks', 'Customer Research Archive'])->count() === 4,
    'chapters' => BookStack\Entities\Models\Chapter::query()->whereIn('name', ['Triage Workflows', 'Launch Readiness', 'Messaging Library', 'Incident Response', 'Deployment Notes', 'Interview Summaries'])->count() === 6,
    'pages' => BookStack\Entities\Models\Page::query()->whereIn('name', ['Priority Matrix', 'Refund Exceptions', 'Glossary – Acronyms & Escalation Codes', 'Go-To-Market Checklist', 'Beta Feedback Summary', 'Customer Email Draft', 'API Latency Runbook', 'Database Failover Steps', 'Weekly Release Checklist', 'Acme Corp Admin Interview', 'Northwind Support Lead Interview', 'Research Backlog Parking Lot'])->count() === 12,
    'users' => BookStack\Users\Models\User::query()->whereIn('email', ['admin@admin.com', 'ari.editor@example.test', 'mina.viewer@example.test'])->count() === 3,
    'admin_password' => Illuminate\Support\Facades\Hash::check('password', $admin->password),
    'admin_auth' => Illuminate\Support\Facades\Auth::attempt(['email' => 'admin@admin.com', 'password' => 'password']),
    'search_phrase' => BookStack\Entities\Models\Page::query()->where('text', 'like', '%bluebird escalation%')->count() === 1,
    'entity_slugs' => BookStack\Entities\Models\Bookshelf::query()->whereNull('slug')->orWhere('slug', '')->count() === 0
        && BookStack\Entities\Models\Book::query()->whereNull('slug')->orWhere('slug', '')->count() === 0
        && BookStack\Entities\Models\Chapter::query()->whereNull('slug')->orWhere('slug', '')->count() === 0
        && BookStack\Entities\Models\Page::query()->whereNull('slug')->orWhere('slug', '')->count() === 0,
    'priority_url' => $support?->slug === 'support-playbooks'
        && $priority?->slug === 'priority-matrix'
        && str_ends_with($priority?->getUrl() ?? '', '/books/support-playbooks/page/priority-matrix'),
    'chapter_url' => $triage?->slug === 'triage-workflows'
        && str_ends_with($triage?->getUrl() ?? '', '/books/support-playbooks/chapter/triage-workflows'),
    'route_lookup' => app(BookStack\Entities\Queries\BookQueries::class)->findVisibleBySlugOrFail('support-playbooks')->id === $support->id
        && app(BookStack\Entities\Queries\PageQueries::class)->findVisibleBySlugsOrFail('support-playbooks', 'priority-matrix')->id === $priority->id
        && app(BookStack\Entities\Queries\ChapterQueries::class)->findVisibleBySlugsOrFail('support-playbooks', 'triage-workflows')->id === $triage->id,
];

foreach ($checks as $name => $ok) {
    if (!$ok) {
        fwrite(STDERR, "Seed verify failed: {$name}\n");
        exit(1);
    }
}

echo "Seed verify passed: 2 shelves, 4 books, 6 chapters, 12 pages, 3 users, admin login valid, bluebird phrase present, slugs/routes valid.\n";
