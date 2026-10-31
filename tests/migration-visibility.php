<?php
declare(strict_types=1);
require __DIR__.'/harness.php';
require __DIR__.'/migration-fixture.php';
use Hostider\Migration\{SnapshotValidator, ValidationReport};

function visibilityReport(array $rows): ValidationReport { return (new SnapshotValidator())->validate(migrationEnvelopes($rows)); }
function visibilityRows(string $id, array $visibility): array
{
    return migrationChange(migrationFixture(), $id, static function(array &$r) use ($visibility): void { $r['payload']['visibility'] = $visibility; });
}
test('F6-A P1 public reply with known PRIVATE is an explicit visibility conflict', function(): void {
    $report = visibilityReport(visibilityRows('public-a', migrationKnown('PRIVATE')));
    same(false, $report->eligible()); same(true, in_array('MESSAGE_VISIBILITY_CONFLICT', $report->reasons, true));
});
test('F6-A P1 public reply with known PUBLIC remains valid and unchanged', function(): void {
    $rows = visibilityRows('public-a', migrationKnown('PUBLIC')); same(true, visibilityReport($rows)->eligible());
    foreach ($rows as $r) { if ($r['source_id'] === 'public-a') { same('PUBLIC_REPLY', $r['payload']['kind']); same(migrationKnown('PUBLIC'), $r['payload']['visibility']); } }
});
test('F6-A P1 private internal note with verified source STAFF remains archive only', function(): void {
    $rows = visibilityRows('private-a', migrationKnown('PRIVATE')); $report = visibilityReport($rows);
    same(true, $report->eligible()); same(false, $report->jsonSerialize()['effects_allowed']);
    foreach ($rows as $r) { if ($r['source_id'] === 'private-a') { same('INTERNAL_NOTE', $r['payload']['kind']); same(migrationKnown('PRIVATE'), $r['payload']['visibility']); } }
    same(false, array_key_exists('client_visible', $report->jsonSerialize()));
});
test('F6-A P1 internal note with PUBLIC remains rejected', function(): void {
    same('PRIVATE_NOTE_EXPOSURE', raises(InvalidArgumentException::class, fn() => visibilityReport(visibilityRows('private-a', migrationKnown('PUBLIC'))))->getMessage());
});
test('F6-A P1 all four uncertain internal note visibility states are rejected', function(): void {
    foreach (['UNKNOWN', 'MISSING', 'CONFLICTING', 'UNVERIFIABLE'] as $state) {
        same('PRIVATE_NOTE_EXPOSURE', raises(InvalidArgumentException::class, fn() => visibilityReport(visibilityRows('private-a', ['state' => $state, 'value' => null])))->getMessage());
    }
});
test('F6-A P1 all four uncertain public reply states remain review without normalization', function(): void {
    foreach (['UNKNOWN', 'MISSING', 'CONFLICTING', 'UNVERIFIABLE'] as $state) {
        $rows = visibilityRows('public-a', ['state' => $state, 'value' => null]); $report = visibilityReport($rows);
        same(false, $report->eligible()); same(true, in_array('MESSAGE_VISIBILITY_CONFLICT', $report->reasons, true));
        same(true, in_array('VALUE_'.$state, $report->reasons, true));
        foreach ($rows as $r) { if ($r['source_id'] === 'public-a') { same(['state' => $state, 'value' => null], $r['payload']['visibility']); } }
    }
});
test('F6-A P1 declared STAFF does not verify a customer source author for internal notes', function(): void {
    $rows = migrationChange(migrationFixture(), 'private-a', static function(array &$r): void { $r['payload']['author'] = 'user-a'; });
    same('PRIVATE_NOTE_EXPOSURE', raises(InvalidArgumentException::class, fn() => visibilityReport($rows))->getMessage());
});
test('F6-A P1 attachment to inconsistent message never makes snapshot public eligible in any order', function(): void {
    $rows = visibilityRows('public-a', migrationKnown('PRIVATE'));
    same(1, count(array_filter($rows, static fn(array $r): bool => $r['entity'] === 'attachment' && $r['payload']['message'] === 'public-a')));
    foreach ([$rows, array_reverse($rows)] as $ordered) {
        $report = visibilityReport($ordered); same(false, $report->eligible()); same(false, $report->jsonSerialize()['effects_allowed']);
        same(true, in_array('MESSAGE_VISIBILITY_CONFLICT', $report->reasons, true));
        same(false, array_key_exists('client_visible', $report->jsonSerialize()));
    }
});
runTests();
